<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Enum;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => '⏳ Pending',
            self::InProgress => '🔄 In Progress',
            self::Completed => '✅ Completed',
            self::Cancelled => '❌ Cancelled',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
