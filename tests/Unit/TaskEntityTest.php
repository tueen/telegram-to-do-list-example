<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Entity\Category;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;

class TaskEntityTest extends TestCase
{
    public function testTaskInstantiationAndHooks(): void
    {
        $category = new Category(1001, 'Work Project', '💼');
        $deadline = new DateTimeImmutable('+3 days');

        $task = new Task(
            chatId: 1001,
            title: 'Implement telegram bot database module',
            description: 'Design table structures and connect to repositories',
            priority: TaskPriority::High,
            category: $category,
            deadline: $deadline,
        );

        $this->assertSame(1001, $task->chatId);
        $this->assertSame('Implement telegram bot database module', $task->title);
        $this->assertSame('Implement telegram bot da...', $task->shortTitle);
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame('⏳ Pending', $task->statusBadge);
        $this->assertSame('🟠 High', $task->priorityBadge);
        $this->assertFalse($task->isDeleted);
        $this->assertFalse($task->isOverdue);
        $this->assertNotEmpty($task->publicId);
    }

    public function testOverduePropertyHook(): void
    {
        $pastDeadline = new DateTimeImmutable('-2 hours');
        $task = new Task(
            chatId: 1001,
            title: 'Send email to client',
            deadline: $pastDeadline,
        );

        $this->assertTrue($task->isOverdue);

        $task->markCompleted();
        $this->assertFalse($task->isOverdue);
        $this->assertNotNull($task->completedAt);
    }

    public function testLifecycleTransitions(): void
    {
        $task = new Task(chatId: 1001, title: 'Test transitions');

        $task->markInProgress();
        $this->assertSame(TaskStatus::InProgress, $task->status);

        $task->markCompleted();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completedAt);

        $task->markPending();
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertNull($task->completedAt);

        $task->softDelete();
        $this->assertTrue($task->isDeleted);
        $this->assertNotNull($task->deletedAt);

        $task->restore();
        $this->assertFalse($task->isDeleted);
        $this->assertNull($task->deletedAt);
    }
}
