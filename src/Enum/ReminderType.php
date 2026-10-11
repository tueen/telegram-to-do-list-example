<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Enum;

enum ReminderType: string
{
    case Scheduled = 'scheduled';
    case DeadlineApproaching = 'deadline_approaching';
    case OverdueAlert = 'overdue_alert';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled Reminder',
            self::DeadlineApproaching => 'Approaching Deadline',
            self::OverdueAlert => 'Overdue Task Alert',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Scheduled => '⏰ Reminder',
            self::DeadlineApproaching => '⚠️ Approaching',
            self::OverdueAlert => '🚨 Overdue',
        };
    }
}
