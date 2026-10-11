<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

class TaskRepositoryTest extends TestCase
{
    private DatabaseService $db;
    private int $testChatId = 999888;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
    }

    public function testTaskCrudAndFiltering(): void
    {
        // 1. Seed categories
        $categories = $this->db->categories()->findByChat($this->testChatId);
        $this->assertCount(4, $categories);
        $workCategory = $categories[0];

        // 2. Create tasks
        $t1 = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Buy espresso beans',
            description: 'From local coffee shop near office',
            priority: TaskPriority::Low,
            category: $workCategory,
            deadline: new DateTimeImmutable('+1 day'),
        );

        $t2 = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Send urgent financial report',
            description: 'Review monthly invoices',
            priority: TaskPriority::Urgent,
            category: $workCategory,
            deadline: new DateTimeImmutable('+2 hours'),
        );

        $t3 = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Practice PHP 8.4 property hooks',
            priority: TaskPriority::High,
        );

        // Mark t1 completed
        $t1->markCompleted();
        $this->db->tasks()->save($t1);

        // 3. Test filtering by status
        $completedTasks = $this->db->tasks()->findActiveByChat($this->testChatId, status: TaskStatus::Completed);
        $this->assertCount(1, $completedTasks);
        $this->assertSame($t1->id, $completedTasks[0]->id);

        $pendingTasks = $this->db->tasks()->findActiveByChat($this->testChatId, status: TaskStatus::Pending);
        $this->assertCount(2, $pendingTasks);

        // 4. Test filtering by priority
        $urgentTasks = $this->db->tasks()->findActiveByChat($this->testChatId, priority: TaskPriority::Urgent);
        $this->assertCount(1, $urgentTasks);
        $this->assertSame($t2->id, $urgentTasks[0]->id);

        // 5. Test search query
        $searchResults = $this->db->tasks()->findActiveByChat($this->testChatId, search: 'hooks');
        $this->assertCount(1, $searchResults);
        $this->assertSame($t3->id, $searchResults[0]->id);

        // 6. Test statistics
        $stats = $this->db->tasks()->getStats($this->testChatId);
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['completed']);

        // 7. Test trash and restore
        $t3->softDelete();
        $this->db->tasks()->save($t3);

        $activeAfterDelete = $this->db->tasks()->findActiveByChat($this->testChatId);
        $this->assertCount(2, $activeAfterDelete);

        $trashTasks = $this->db->tasks()->findTrashByChat($this->testChatId);
        $this->assertCount(1, $trashTasks);
        $this->assertSame($t3->id, $trashTasks[0]->id);

        // Restore
        $t3->restore();
        $this->db->tasks()->save($t3);

        $trashAfterRestore = $this->db->tasks()->findTrashByChat($this->testChatId);
        $this->assertCount(0, $trashAfterRestore);
    }
}
