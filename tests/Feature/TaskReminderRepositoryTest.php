<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

class TaskReminderRepositoryTest extends TestCase
{
    private DatabaseService $db;
    private int $testChatId = 333444555;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
    }

    public function testReminderCreationAndDueQuery(): void
    {
        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Prepare presentation slides',
            priority: TaskPriority::High,
        );

        $pastTime = new DateTimeImmutable('-15 minutes');
        $futureTime = new DateTimeImmutable('+2 hours');

        $dueReminder = $this->db->reminders()->createReminder(
            task: $task,
            remindAt: $pastTime,
            type: ReminderType::Scheduled,
        );

        $futureReminder = $this->db->reminders()->createReminder(
            task: $task,
            remindAt: $futureTime,
            type: ReminderType::DeadlineApproaching,
        );

        $this->assertNotNull($dueReminder->id);
        $this->assertNotNull($futureReminder->id);

        // Find due reminders
        $dueList = $this->db->reminders()->findDueReminders(new DateTimeImmutable());
        $dueIds = array_map(fn($r) => $r->id, $dueList);

        $this->assertContains($dueReminder->id, $dueIds);
        $this->assertNotContains($futureReminder->id, $dueIds);

        // Find upcoming for chat
        $upcoming = $this->db->reminders()->findUpcomingForChat($this->testChatId);
        $upcomingIds = array_map(fn($r) => $r->id, $upcoming);

        $this->assertContains($futureReminder->id, $upcomingIds);
        $this->assertNotContains($dueReminder->id, $upcomingIds);
    }

    public function testDeleteForTask(): void
    {
        $task = $this->db->tasks()->createTask(
            chatId: $this->testChatId,
            title: 'Task with reminders to delete',
        );

        $reminder = $this->db->reminders()->createReminder(
            task: $task,
            remindAt: new DateTimeImmutable('+1 day'),
        );

        $this->assertNotNull($reminder->id);

        $this->db->reminders()->deleteForTask($task->id);

        $found = $this->db->reminders()->find($reminder->id);
        $this->assertNull($found);
    }
}
