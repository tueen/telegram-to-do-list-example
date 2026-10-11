<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Entity\TaskReminder;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;

class TaskReminderEntityTest extends TestCase
{
    public function testTaskReminderInstantiation(): void
    {
        $task = new Task(chatId: 555666, title: 'Doctor appointment');
        $remindAt = new DateTimeImmutable('+1 day');

        $reminder = new TaskReminder($task, $remindAt, ReminderType::Scheduled);

        $this->assertSame($task, $reminder->task);
        $this->assertSame(555666, $reminder->chatId);
        $this->assertSame($remindAt, $reminder->remindAt);
        $this->assertSame(ReminderType::Scheduled, $reminder->type);
        $this->assertFalse($reminder->isSent);
        $this->assertSame(0, $reminder->snoozeCount);
        $this->assertNull($reminder->sentAt);
        $this->assertNull($reminder->snoozedUntil);
    }

    public function testMarkSent(): void
    {
        $task = new Task(chatId: 555666, title: 'Meeting reminder');
        $reminder = new TaskReminder($task, new DateTimeImmutable('-10 minutes'));

        $this->assertFalse($reminder->isSent);
        $reminder->markSent();

        $this->assertTrue($reminder->isSent);
        $this->assertNotNull($reminder->sentAt);
    }

    public function testSnoozeIncrementsCountAndUpdatesTarget(): void
    {
        $task = new Task(chatId: 555666, title: 'Dentist appointment');
        $initialTime = new DateTimeImmutable('+1 hour');
        $reminder = new TaskReminder($task, $initialTime);

        $snoozeTarget = new DateTimeImmutable('+2 hours');
        $reminder->snooze($snoozeTarget);

        $this->assertSame($snoozeTarget, $reminder->remindAt);
        $this->assertSame($snoozeTarget, $reminder->snoozedUntil);
        $this->assertSame(1, $reminder->snoozeCount);
        $this->assertFalse($reminder->isSent);

        // Snooze second time
        $secondSnooze = new DateTimeImmutable('+3 hours');
        $reminder->snooze($secondSnooze);
        $this->assertSame(2, $reminder->snoozeCount);
        $this->assertSame($secondSnooze, $reminder->remindAt);
    }
}
