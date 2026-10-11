<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Service;

use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;

final class UserSettingsService
{
    public function __construct(
        private readonly DatabaseService $db = new DatabaseService()
    ) {}

    public function getTimezone(int $chatId): string
    {
        return (string) $this->db->chats()->getMeta($chatId, 'timezone', 'Asia/Tehran');
    }

    public function setTimezone(int $chatId, string $timezone): void
    {
        $this->db->chats()->setMeta($chatId, 'timezone', $timezone, group: 'settings');
    }

    public function areNotificationsEnabled(int $chatId): bool
    {
        return (bool) $this->db->chats()->getMeta($chatId, 'notifications_enabled', true);
    }

    public function setNotificationsEnabled(int $chatId, bool $enabled): void
    {
        $this->db->chats()->setMeta($chatId, 'notifications_enabled', $enabled, group: 'settings');
    }

    public function getSortPreference(int $chatId): string
    {
        return (string) $this->db->chats()->getMeta($chatId, 'sort_preference', 'newest');
    }

    public function setSortPreference(int $chatId, string $sort): void
    {
        $this->db->chats()->setMeta($chatId, 'sort_preference', $sort, group: 'settings');
    }

    public function getFilterStatus(int $chatId): ?TaskStatus
    {
        $val = $this->db->chats()->getMeta($chatId, 'filter_status', null);
        return is_string($val) ? TaskStatus::tryFrom($val) : null;
    }

    public function setFilterStatus(int $chatId, ?TaskStatus $status): void
    {
        if ($status === null) {
            $this->db->chats()->deleteMeta($chatId, 'filter_status');
        } else {
            $this->db->chats()->setMeta($chatId, 'filter_status', $status->value, group: 'filters');
        }
    }

    public function getFilterPriority(int $chatId): ?TaskPriority
    {
        $val = $this->db->chats()->getMeta($chatId, 'filter_priority', null);
        return is_string($val) ? TaskPriority::tryFrom($val) : null;
    }

    public function setFilterPriority(int $chatId, ?TaskPriority $priority): void
    {
        if ($priority === null) {
            $this->db->chats()->deleteMeta($chatId, 'filter_priority');
        } else {
            $this->db->chats()->setMeta($chatId, 'filter_priority', $priority->value, group: 'filters');
        }
    }

    public function getFilterCategory(int $chatId): ?int
    {
        $val = $this->db->chats()->getMeta($chatId, 'filter_category', null);
        return $val !== null ? (int)$val : null;
    }

    public function setFilterCategory(int $chatId, ?int $categoryId): void
    {
        if ($categoryId === null) {
            $this->db->chats()->deleteMeta($chatId, 'filter_category');
        } else {
            $this->db->chats()->setMeta($chatId, 'filter_category', $categoryId, group: 'filters');
        }
    }

    public function clearFilters(int $chatId): void
    {
        $this->db->chats()->deleteMeta($chatId, 'filter_status');
        $this->db->chats()->deleteMeta($chatId, 'filter_priority');
        $this->db->chats()->deleteMeta($chatId, 'filter_category');
    }
}
