<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Service;

use DateTimeImmutable;
use Throwable;
use Tueen\Telegram\Formatting\Text;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Entity\TaskReminder;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Support\DateTimeHelper;

final class ReminderService
{
    public function __construct(
        private readonly DatabaseService $db = new DatabaseService(),
        private readonly UserSettingsService $settings = new UserSettingsService(),
    ) {}

    /**
     * Checks all pending reminders and sends notifications through the Telegram client.
     *
     * @return int Count of reminders dispatched
     */
    public function processDueReminders(Telegram $bot): int
    {
        $now = new DateTimeImmutable();
        $dueReminders = $this->db->reminders()->findDueReminders($now, limit: 50);
        $sentCount = 0;

        foreach ($dueReminders as $reminder) {
            $task = $reminder->task;
            $chatId = $reminder->chatId;

            // Check if user disabled notifications
            if (!$this->settings->areNotificationsEnabled($chatId)) {
                $reminder->markSent();
                $this->db->reminders()->save($reminder);
                continue;
            }

            // Verify chat is accessible in database
            $chat = $this->db->chats()->find($chatId);
            if ($chat !== null && !$chat->accessible) {
                // User blocked the bot, do not attempt to send
                $reminder->markSent();
                $this->db->reminders()->save($reminder);
                continue;
            }

            try {
                $this->sendReminderMessage($bot, $reminder);
                $reminder->markSent();
                $this->db->reminders()->save($reminder);
                $sentCount++;
            } catch (Throwable $e) {
                // If Telegram reports bot was blocked by user (403 forbidden)
                if (str_contains(strtolower($e->getMessage()), 'blocked') || str_contains(strtolower($e->getMessage()), 'forbidden')) {
                    $this->db->chats()->setAccessible($chatId, false);
                }
            }
        }

        return $sentCount;
    }

    /**
     * Sends the interactive reminder alert to the Telegram user.
     */
    public function sendReminderMessage(Telegram $bot, TaskReminder $reminder): void
    {
        $task = $reminder->task;
        $chatId = $reminder->chatId;
        $tz = $this->settings->getTimezone($chatId);

        $categoryName = $task->category !== null ? $task->category->displayName : 'Uncategorized';
        $deadlineStr = $task->deadline !== null ? DateTimeHelper::formatDateTime($task->deadline, $tz) : 'Not specified';

        $typeHeader = match ($reminder->type) {
            ReminderType::DeadlineApproaching => "⚠️ <b>Approaching Task Deadline!</b>",
            ReminderType::OverdueAlert => "🚨 <b>Overdue Task Alert!</b>",
            default => "⏰ <b>Task Reminder</b>",
        };

        $text = "{$typeHeader}\n\n"
            . "📌 <b>Title:</b> " . htmlspecialchars($task->title, ENT_QUOTES) . "\n"
            . "📂 <b>Category:</b> {$categoryName}\n"
            . "🎯 <b>Priority:</b> {$task->priorityBadge}\n"
            . "⏳ <b>Status:</b> {$task->statusBadge}\n"
            . "📅 <b>Deadline:</b> {$deadlineStr}\n";

        if (!empty($task->description)) {
            $text .= "\n📝 <b>Description:</b>\n" . htmlspecialchars($task->description, ENT_QUOTES) . "\n";
        }

        $keyboard = InlineKeyboard::make()
            ->row()
            ->action('✅ Mark Completed', "task:quick_done_{$task->id}")
            ->action('👁 View Details', "task:view_{$task->id}")
            ->row()
            ->action('💤 Snooze 15 min', "remind:snooze:{$reminder->id}:15")
            ->action('💤 Snooze 1 hour', "remind:snooze:{$reminder->id}:60")
            ->row()
            ->action('💤 Snooze 1 day (Tomorrow)', "remind:snooze:{$reminder->id}:1440")
            ->build();

        $bot->sendMessage(
            chatId: $chatId,
            text: $text,
            parseMode: 'HTML',
            replyMarkup: $keyboard,
        );
    }

    /**
     * Snoozes a reminder by the specified amount of minutes.
     */
    public function snoozeReminder(int $reminderId, int $minutes): ?TaskReminder
    {
        /** @var TaskReminder|null $reminder */
        $reminder = $this->db->reminders()->find($reminderId);
        if ($reminder === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $target = $now->modify("+{$minutes} minutes");

        $reminder->snooze($target);
        $this->db->reminders()->save($reminder);

        $task = $reminder->task;
        $task->reminderAt = $target;
        $this->db->tasks()->save($task);

        return $reminder;
    }
}
