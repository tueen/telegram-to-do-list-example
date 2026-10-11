<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Support\DateTimeHelper;

class DateTimeHelperTest extends TestCase
{
    public function testFormatDateTimeWithTimezone(): void
    {
        $utcDate = new DateTimeImmutable('2026-10-15 12:00:00', new DateTimeZone('UTC'));

        $formattedUtc = DateTimeHelper::formatDateTime($utcDate, 'UTC');
        $this->assertSame('2026-10-15 12:00', $formattedUtc);

        // Tehran is UTC+3:30 => 15:30
        $formattedTehran = DateTimeHelper::formatDateTime($utcDate, 'Asia/Tehran');
        $this->assertSame('2026-10-15 15:30', $formattedTehran);

        // Null returns placeholder dash
        $this->assertSame('—', DateTimeHelper::formatDateTime(null));
    }

    public function testFormatDate(): void
    {
        $date = new DateTimeImmutable('2026-10-15 12:00:00', new DateTimeZone('UTC'));
        $this->assertSame('2026-10-15', DateTimeHelper::formatDate($date));
        $this->assertSame('—', DateTimeHelper::formatDate(null));
    }

    public function testParseUserDateTimeKeywords(): void
    {
        $todayParsed = DateTimeHelper::parseUserDateTime('today', 'UTC');
        $this->assertNotNull($todayParsed);
        $this->assertSame((new DateTimeImmutable('today 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'), $todayParsed->format('Y-m-d H:i:s'));

        $tomorrowParsed = DateTimeHelper::parseUserDateTime('tomorrow', 'UTC');
        $this->assertNotNull($tomorrowParsed);
        $this->assertSame((new DateTimeImmutable('tomorrow 18:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'), $tomorrowParsed->format('Y-m-d H:i:s'));

        $this->assertNull(DateTimeHelper::parseUserDateTime(''));
        $this->assertNull(DateTimeHelper::parseUserDateTime('not a valid date string 123456'));
    }

    public function testParseUserDateTimeSpecificString(): void
    {
        $parsed = DateTimeHelper::parseUserDateTime('2026-12-25 10:00', 'UTC');
        $this->assertNotNull($parsed);
        $this->assertSame('2026-12-25 10:00:00', $parsed->format('Y-m-d H:i:s'));
    }

    public function testHumanRelative(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $todayFuture = $now->setTime(23, 50, 0);

        $relativeToday = DateTimeHelper::humanRelative($todayFuture, 'UTC');
        $this->assertStringContainsString('Today at', $relativeToday);

        $tomorrow = $now->modify('+1 day')->setTime(10, 0, 0);
        $relativeTomorrow = DateTimeHelper::humanRelative($tomorrow, 'UTC');
        $this->assertSame('Tomorrow at 10:00', $relativeTomorrow);

        $yesterday = $now->modify('-1 day')->setTime(10, 0, 0);
        $relativeYesterday = DateTimeHelper::humanRelative($yesterday, 'UTC');
        $this->assertSame('Yesterday at 10:00', $relativeYesterday);
    }
}
