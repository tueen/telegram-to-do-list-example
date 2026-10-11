<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Enum;

use DateTimeImmutable;

enum TaskRecurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'One-time (No recurrence)',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::None => '⚪ One-time',
            self::Daily => '🔁 Daily',
            self::Weekly => '🔁 Weekly',
            self::Monthly => '🔁 Monthly',
        };
    }

    public function calculateNextDate(DateTimeImmutable $currentDeadline): ?DateTimeImmutable
    {
        return match ($this) {
            self::Daily => $currentDeadline->modify('+1 day'),
            self::Weekly => $currentDeadline->modify('+1 week'),
            self::Monthly => $currentDeadline->modify('+1 month'),
            self::None => null,
        };
    }
}
