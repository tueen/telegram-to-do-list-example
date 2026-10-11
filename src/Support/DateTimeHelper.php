<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

final class DateTimeHelper
{
    public const SUPPORTED_TIMEZONES = [
        'Asia/Tehran' => '🇮🇷 Tehran (UTC+3:30)',
        'UTC' => '🌐 Greenwich (UTC)',
        'Asia/Dubai' => '🇦🇪 Dubai (UTC+4:00)',
        'Europe/Istanbul' => '🇹🇷 Istanbul (UTC+3:00)',
        'Europe/Berlin' => '🇩🇪 Berlin (UTC+1:00)',
        'Europe/London' => '🇬🇧 London (UTC+0:00)',
        'America/New_York' => '🇺🇸 New York (UTC-5:00)',
        'America/Los_Angeles' => '🇺🇸 Los Angeles (UTC-8:00)',
    ];

    public static function formatDateTime(?DateTimeInterface $date, string $timezone = 'UTC'): string
    {
        if ($date === null) {
            return '—';
        }

        try {
            $tz = new DateTimeZone($timezone);
            $local = $date instanceof DateTimeImmutable
                ? $date->setTimezone($tz)
                : DateTimeImmutable::createFromInterface($date)->setTimezone($tz);

            return $local->format('Y-m-d H:i');
        } catch (Throwable) {
            return $date->format('Y-m-d H:i');
        }
    }

    public static function formatDate(?DateTimeInterface $date, string $timezone = 'UTC'): string
    {
        if ($date === null) {
            return '—';
        }

        try {
            $tz = new DateTimeZone($timezone);
            $local = $date instanceof DateTimeImmutable
                ? $date->setTimezone($tz)
                : DateTimeImmutable::createFromInterface($date)->setTimezone($tz);

            return $local->format('Y-m-d');
        } catch (Throwable) {
            return $date->format('Y-m-d');
        }
    }

    public static function humanRelative(DateTimeInterface $date, string $timezone = 'UTC'): string
    {
        try {
            $tz = new DateTimeZone($timezone);
            $now = new DateTimeImmutable('now', $tz);
            $target = DateTimeImmutable::createFromInterface($date)->setTimezone($tz);

            $diff = $now->diff($target);
            $isPast = $target < $now;

            $timeStr = $target->format('H:i');
            $days = (int)$diff->format('%r%a');

            if ($target->format('Y-m-d') === $now->format('Y-m-d')) {
                return $isPast ? "Today at {$timeStr} (Past)" : "Today at {$timeStr}";
            }

            $tomorrow = $now->modify('+1 day');
            if ($target->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
                return "Tomorrow at {$timeStr}";
            }

            $yesterday = $now->modify('-1 day');
            if ($target->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                return "Yesterday at {$timeStr}";
            }

            if ($isPast) {
                return $target->format('Y-m-d H:i') . " (Overdue)";
            }

            if ($days > 0 && $days <= 7) {
                return "In {$days} days at {$timeStr}";
            }

            return $target->format('Y-m-d H:i');
        } catch (Throwable) {
            return $date->format('Y-m-d H:i');
        }
    }

    /**
     * Parses standard user inputs like "tomorrow 15:00", "2026-10-15 14:00", "+2 days", "+3 hours".
     */
    public static function parseUserDateTime(string $input, string $timezone = 'UTC'): ?DateTimeImmutable
    {
        $clean = trim($input);
        if ($clean === '') {
            return null;
        }

        try {
            $tz = new DateTimeZone($timezone);

            // Handle relative shortcuts
            $lower = strtolower($clean);
            if ($lower === 'today') {
                return (new DateTimeImmutable('today 23:59:59', $tz))->setTimezone(new DateTimeZone('UTC'));
            }
            if ($lower === 'tomorrow') {
                return (new DateTimeImmutable('tomorrow 18:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
            }
            if ($lower === 'next week') {
                return (new DateTimeImmutable('+7 days 12:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
            }

            // If time only (e.g. 18:00)
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $clean, $m)) {
                $h = (int)$m[1];
                $i = (int)$m[2];
                $dt = new DateTimeImmutable('now', $tz);
                $target = $dt->setTime($h, $i, 0);
                if ($target <= $dt) {
                    $target = $target->modify('+1 day');
                }
                return $target->setTimezone(new DateTimeZone('UTC'));
            }

            // Normal DateTimeImmutable parse in user's timezone
            $dt = new DateTimeImmutable($clean, $tz);
            return $dt->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }
}
