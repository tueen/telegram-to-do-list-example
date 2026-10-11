<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;

class UserSettingsServiceTest extends TestCase
{
    private DatabaseService $db;
    private UserSettingsService $settings;
    private int $testChatId = 777111222;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
        $this->settings = new UserSettingsService($this->db);
        $this->db->chats()->findOrCreate($this->testChatId, username: 'testuser');
    }

    public function testTimezoneSettings(): void
    {
        // Default timezone is Asia/Tehran
        $this->assertSame('Asia/Tehran', $this->settings->getTimezone($this->testChatId));

        $this->settings->setTimezone($this->testChatId, 'Europe/London');
        $this->assertSame('Europe/London', $this->settings->getTimezone($this->testChatId));
    }

    public function testNotificationToggles(): void
    {
        // Default is enabled (true)
        $this->assertTrue($this->settings->areNotificationsEnabled($this->testChatId));

        $this->settings->setNotificationsEnabled($this->testChatId, false);
        $this->assertFalse($this->settings->areNotificationsEnabled($this->testChatId));

        $this->settings->setNotificationsEnabled($this->testChatId, true);
        $this->assertTrue($this->settings->areNotificationsEnabled($this->testChatId));
    }

    public function testSortPreferences(): void
    {
        $this->assertSame('newest', $this->settings->getSortPreference($this->testChatId));

        $this->settings->setSortPreference($this->testChatId, 'deadline');
        $this->assertSame('deadline', $this->settings->getSortPreference($this->testChatId));
    }

    public function testFiltersAndClear(): void
    {
        $this->assertNull($this->settings->getFilterStatus($this->testChatId));
        $this->assertNull($this->settings->getFilterPriority($this->testChatId));
        $this->assertNull($this->settings->getFilterCategory($this->testChatId));

        // Set filters
        $this->settings->setFilterStatus($this->testChatId, TaskStatus::Pending);
        $this->settings->setFilterPriority($this->testChatId, TaskPriority::Urgent);
        $this->settings->setFilterCategory($this->testChatId, 42);

        $this->assertSame(TaskStatus::Pending, $this->settings->getFilterStatus($this->testChatId));
        $this->assertSame(TaskPriority::Urgent, $this->settings->getFilterPriority($this->testChatId));
        $this->assertSame(42, $this->settings->getFilterCategory($this->testChatId));

        // Clear filters
        $this->settings->clearFilters($this->testChatId);

        $this->assertNull($this->settings->getFilterStatus($this->testChatId));
        $this->assertNull($this->settings->getFilterPriority($this->testChatId));
        $this->assertNull($this->settings->getFilterCategory($this->testChatId));
    }
}
