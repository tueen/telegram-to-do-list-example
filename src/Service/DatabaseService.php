<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tueen\Database\Config\ConnectionConfig;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Repository\ChatRepository;
use Tueen\Telegram\Example\ToDoList\Entity\Category;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Entity\TaskReminder;
use Tueen\Telegram\Example\ToDoList\Repository\CategoryRepository;
use Tueen\Telegram\Example\ToDoList\Repository\TaskReminderRepository;
use Tueen\Telegram\Example\ToDoList\Repository\TaskRepository;

/**
 * Singleton database service providing unified access to Tueen DatabaseManager
 * and To-Do List domain repositories.
 */
final class DatabaseService
{
    private static ?self $instance = null;
    private DatabaseManager $manager;

    public function __construct(?DatabaseConfig $config = null)
    {
        if ($config === null) {
            $basePath = dirname(__DIR__, 2);
            $driver = strtolower($_ENV['DB_DRIVER'] ?? 'pdo_sqlite');

            if ($driver === 'pdo_mysql' || $driver === 'mysql') {
                $connection = ConnectionConfig::mysql(
                    dbname: $_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? 'todo_bot',
                    user: $_ENV['DB_USER'] ?? $_ENV['DB_USERNAME'] ?? 'root',
                    password: $_ENV['DB_PASSWORD'] ?? '',
                    host: $_ENV['DB_HOST'] ?? '127.0.0.1',
                    port: (int)($_ENV['DB_PORT'] ?? 3306),
                    charset: $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                );
            } else {
                $rawPath = $_ENV['DB_PATH'] ?? ($basePath . '/storage/database.sqlite');

                if ($rawPath === ':memory:') {
                    $dbPath = ':memory:';
                } elseif (!str_starts_with($rawPath, '/') && !preg_match('/^[a-zA-Z]:[\\\\\/]/', $rawPath)) {
                    $dbPath = $basePath . '/' . ltrim($rawPath, '/\\');
                } else {
                    $dbPath = $rawPath;
                }

                if ($dbPath !== ':memory:') {
                    $dir = dirname($dbPath);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                }

                $connection = ConnectionConfig::sqlite($dbPath);
            }

            // Path to built-in tueen/database entities and our custom entities
            $tueenDbEntities = dirname(__DIR__, 2) . '/vendor/tueen/database/src/Entity';
            if (!is_dir($tueenDbEntities)) {
                $tueenDbEntities = dirname(__DIR__, 3) . '/database/src/Entity';
            }
            $appEntities = dirname(__DIR__) . '/Entity';

            $config = new DatabaseConfig(
                connection: $connection,
                entityPaths: array_values(array_filter([$tueenDbEntities, $appEntities], 'is_dir')),
                autoMigrate: true,
                devMode: (bool)($_ENV['APP_DEBUG'] ?? false),
            );
        }

        $this->manager = DatabaseManager::create($config);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function getManager(): DatabaseManager
    {
        return $this->manager;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->manager->getEntityManager();
    }

    public function chats(): ChatRepository
    {
        return $this->manager->chats();
    }

    public function tasks(): TaskRepository
    {
        /** @var TaskRepository */
        return $this->getEntityManager()->getRepository(Task::class);
    }

    public function categories(): CategoryRepository
    {
        /** @var CategoryRepository */
        return $this->getEntityManager()->getRepository(Category::class);
    }

    public function reminders(): TaskReminderRepository
    {
        /** @var TaskReminderRepository */
        return $this->getEntityManager()->getRepository(TaskReminder::class);
    }

    /**
     * Executes declarative schema sync on demand.
     *
     * @return list<string>
     */
    public function syncSchema(): array
    {
        return $this->manager->syncSchema();
    }
}
