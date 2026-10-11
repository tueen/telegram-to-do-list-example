<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Enum;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Low => '🟢 Low',
            self::Medium => '🟡 Medium',
            self::High => '🟠 High',
            self::Urgent => '🔴 Urgent',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }
}
