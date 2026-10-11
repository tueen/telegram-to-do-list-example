<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskRecurrence;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;

class EnumTest extends TestCase
{
    public function testTaskPriorityBadgesAndWeights(): void
    {
        $this->assertSame('Low', TaskPriority::Low->label());
        $this->assertSame('🟢 Low', TaskPriority::Low->badge());
        $this->assertSame(1, TaskPriority::Low->weight());

        $this->assertSame('🔴 Urgent', TaskPriority::Urgent->badge());
        $this->assertSame(4, TaskPriority::Urgent->weight());
    }

    public function testTaskStatusProperties(): void
    {
        $this->assertFalse(TaskStatus::Pending->isFinished());
        $this->assertFalse(TaskStatus::InProgress->isFinished());
        $this->assertTrue(TaskStatus::Completed->isFinished());
        $this->assertTrue(TaskStatus::Cancelled->isFinished());

        $this->assertSame('⏳ Pending', TaskStatus::Pending->badge());
        $this->assertSame('✅ Completed', TaskStatus::Completed->badge());
    }

    public function testTaskRecurrenceCalculation(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:00:00');

        $this->assertNull(TaskRecurrence::None->calculateNextDate($now));

        $dailyNext = TaskRecurrence::Daily->calculateNextDate($now);
        $this->assertNotNull($dailyNext);
        $this->assertSame('2026-10-16 12:00:00', $dailyNext->format('Y-m-d H:i:s'));

        $weeklyNext = TaskRecurrence::Weekly->calculateNextDate($now);
        $this->assertNotNull($weeklyNext);
        $this->assertSame('2026-10-22 12:00:00', $weeklyNext->format('Y-m-d H:i:s'));

        $monthlyNext = TaskRecurrence::Monthly->calculateNextDate($now);
        $this->assertNotNull($monthlyNext);
        $this->assertSame('2026-11-15 12:00:00', $monthlyNext->format('Y-m-d H:i:s'));
    }

    public function testReminderTypeBadges(): void
    {
        $this->assertSame('⏰ Reminder', ReminderType::Scheduled->badge());
        $this->assertSame('⚠️ Approaching', ReminderType::DeadlineApproaching->badge());
        $this->assertSame('🚨 Overdue', ReminderType::OverdueAlert->badge());
    }
}
