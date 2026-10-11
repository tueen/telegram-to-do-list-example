<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Controller\MainController;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;

class MainControllerTest extends TestCase
{
    private DatabaseService $db;
    private UserSettingsService $settings;
    private int $testChatId = 11223344;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
        $this->settings = new UserSettingsService($this->db);
        $this->db->chats()->findOrCreate($this->testChatId, username: 'main_user');
    }

    public function testHelpCommandRepliesWithGuide(): void
    {
        $controller = new MainController();
        $bot = Telegram::fake();

        $update = new Update([
            'update_id' => 3001,
            'message' => [
                'message_id' => 1,
                'date' => time(),
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => '/help',
            ],
        ]);

        $controller->onHelp($update, $bot);

        $bot->assertSent('sendMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && str_contains($params['text'], 'Task Manager Bot Guide');
        });
        $bot->assertSentCount(1, 'sendMessage');
    }

    public function testTodayCommandWithNoTasks(): void
    {
        $controller = new MainController();
        $bot = Telegram::fake();

        $update = new Update([
            'update_id' => 3002,
            'message' => [
                'message_id' => 2,
                'date' => time(),
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => '/today',
            ],
        ]);

        $controller->onToday($update, $bot);

        $bot->assertSent('sendMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && str_contains($params['text'], 'No tasks due today');
        });
    }

    public function testTodayCommandWithDueTasks(): void
    {
        $controller = new MainController();
        $bot = Telegram::fake();

        // Ensure user timezone is UTC for predictable test
        $this->settings->setTimezone($this->testChatId, 'UTC');

        // Create a task due today in UTC
        $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Deploy v1 release',
            priority: TaskPriority::Urgent,
            deadline: new DateTimeImmutable('today 18:00:00'),
        );

        $update = new Update([
            'update_id' => 3003,
            'message' => [
                'message_id' => 3,
                'date' => time(),
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => '/today',
            ],
        ]);

        $controller->onToday($update, $bot);

        $bot->assertSent('sendMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && str_contains($params['text'], 'Deploy v1 release');
        });
    }

    public function testStartCommandSendsDashboardAndPreservesUserMessage(): void
    {
        $controller = new MainController();
        $bot = Telegram::fake([
            'sendMessage' => [
                'message_id' => 999,
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => 'Dashboard',
            ],
        ]);

        $update = new Update([
            'update_id' => 3004,
            'message' => [
                'message_id' => 10,
                'date' => time(),
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => '/start',
            ],
        ]);

        $controller->onStart($update, $bot);

        // Assert user's message was NOT deleted
        $bot->assertNotSent('deleteMessage', function (array $params) {
            return (int)$params['message_id'] === 10;
        });

        // Assert dashboard screen was rendered
        $bot->assertSent('sendMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && str_contains($params['text'], 'Task Manager Dashboard');
        });
    }

    public function testStartCommandDeletesPreviousMenuMessageWhenCalledAgain(): void
    {
        $controller = new MainController();
        $bot = Telegram::fake([
            'sendMessage' => [
                'message_id' => 500,
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => 'Dashboard',
            ],
        ]);

        // First /start
        $update1 = new Update([
            'update_id' => 3005,
            'message' => [
                'message_id' => 101,
                'date' => time(),
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => '/start',
            ],
        ]);
        $controller->onStart($update1, $bot);

        // Second /start
        $update2 = new Update([
            'update_id' => 3006,
            'message' => [
                'message_id' => 102,
                'date' => time(),
                'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                'text' => '/start',
            ],
        ]);
        $controller->onStart($update2, $bot);

        // Assert old menu message (500) was deleted on second /start
        $bot->assertSent('deleteMessage', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId && (int)$params['message_id'] === 500;
        });

        // Assert user's command messages (101 and 102) were NOT deleted
        $bot->assertNotSent('deleteMessage', function (array $params) {
            return (int)$params['message_id'] === 101 || (int)$params['message_id'] === 102;
        });
    }

    public function testSnoozeFromCallbackUpdatesReminderAndTask(): void
    {
        $controller = new MainController();
        $bot = Telegram::fake();

        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Task to Snooze',
        );

        $reminder = $this->db->reminders()->createReminder(
            task: $task,
            remindAt: new DateTimeImmutable('+10 minutes'),
        );

        $update = new Update([
            'update_id' => 3007,
            'callback_query' => [
                'id' => 'cb_snooze_123',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 888,
                    'date' => time(),
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => '⏰ Task Reminder',
                ],
                'data' => "remind:snooze_{$reminder->id}_30",
            ],
        ]);

        $controller->onSnoozeFromCallback($update, $bot, $reminder->id, 30);

        $bot->assertSent('answerCallbackQuery', function (array $params) {
            return $params['callback_query_id'] === 'cb_snooze_123'
                && str_contains($params['text'], '30m');
        });

        $bot->assertSent('editMessageText', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && (int)$params['message_id'] === 888
                && str_contains($params['text'], 'Reminder Snoozed');
        });

        // Verify entity updates in database
        $refreshedReminder = $this->db->reminders()->find($reminder->id);
        $this->assertNotNull($refreshedReminder);
        $this->assertSame(1, $refreshedReminder->snoozeCount);

        $refreshedTask = $this->db->tasks()->findForChat($this->testChatId, $task->id);
        $this->assertNotNull($refreshedTask);
        $this->assertNotNull($refreshedTask->reminderAt);
    }

    public function testQuickDoneFromCallbackMarksTaskCompleted(): void
    {
        $controller = new MainController();
        $bot = Telegram::fake();

        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Task to Complete',
        );

        $update = new Update([
            'update_id' => 3008,
            'callback_query' => [
                'id' => 'cb_done_456',
                'from' => ['id' => $this->testChatId, 'is_bot' => false, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 999,
                    'date' => time(),
                    'chat' => ['id' => $this->testChatId, 'type' => 'private'],
                    'text' => '⏰ Task Reminder',
                ],
                'data' => "task:quick_done_{$task->id}",
            ],
        ]);

        $controller->onQuickDoneFromCallback($update, $bot, $task->id);

        $bot->assertSent('answerCallbackQuery', function (array $params) {
            return $params['callback_query_id'] === 'cb_done_456';
        });

        $bot->assertSent('editMessageText', function (array $params) {
            return (int)$params['chat_id'] === $this->testChatId
                && (int)$params['message_id'] === 999
                && str_contains($params['text'], 'Task Completed');
        });

        $refreshedTask = $this->db->tasks()->findForChat($this->testChatId, $task->id);
        $this->assertNotNull($refreshedTask);
        $this->assertSame(\Tueen\Telegram\Example\ToDoList\Enum\TaskStatus::Completed, $refreshedTask->status);
    }
}

