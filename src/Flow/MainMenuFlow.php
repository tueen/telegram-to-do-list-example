<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\Attributes\SingleScreen;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\ReminderService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;

#[Breadcrumb('🏠 Home')]
#[SingleScreen]
class MainMenuFlow extends InteractiveFlow
{
    private DatabaseService $db;
    private UserSettingsService $settings;
    private ReminderService $reminders;

    public function __construct(
        ?DatabaseService $db = null,
        ?UserSettingsService $settings = null,
        ?ReminderService $reminders = null,
    ) {
        $this->db = $db ?? DatabaseService::getInstance();
        $this->settings = $settings ?? new UserSettingsService($this->db);
        $this->reminders = $reminders ?? new ReminderService($this->db, $this->settings);
    }

    #[\Override]
    public function onCreate(): void
    {
        // Ensure default categories exist for this user
        if ($this->chatId !== null) {
            $this->db->categories()->findByChat($this->chatId);
        }
    }

    #[\Override]
    public function render(): Screen
    {
        $stats = $this->db->tasks()->getStats($this->chatId);

        $text = "📋 <b>Task Manager Dashboard (To-Do List)</b>\n\n"
            . "Welcome to your smart task management assistant.\n"
            . "Use the menu below to organize, categorize, and schedule your tasks.\n\n"
            . "📊 <b>Task Summary:</b>\n"
            . "▪️ 📝 Total Tasks: <b>{$stats['total']}</b>\n"
            . "▪️ ⏳ Pending: <b>{$stats['pending']}</b>\n"
            . "▪️ 🔄 In Progress: <b>{$stats['inProgress']}</b>\n"
            . "▪️ ✅ Completed: <b>{$stats['completed']}</b>\n";

        if ($stats['overdue'] > 0) {
            $text .= "▪️ 🚨 <b>Overdue Tasks: {$stats['overdue']}</b> ⚠️\n";
        }

        if ($stats['trash'] > 0) {
            $text .= "▪️ 🗑 Trash Bin: <b>{$stats['trash']}</b>\n";
        }

        $screen = Screen::make($text)->parseMode('HTML');

        $screen->inline(function (InlineKeyboard $k) {
            $k->row()
                ->action('➕ Add New Task', 'action:task_new')
                ->action('📋 View Tasks', 'action:task_list')
                ->row()
                ->action('📂 Categories', 'action:categories')
                ->action('🔍 Search Tasks', 'action:task_search')
                ->row()
                ->action('🗑 Trash Bin', 'action:trash')
                ->action('⚙️ Settings & Timezone', 'action:settings');
        });

        return $screen;
    }

    #[Action('action:task_new')]
    public function onTaskNew(): void
    {
        $this->push(TaskCreateFlow::class);
    }

    #[Action('action:task_list')]
    public function onTaskList(): void
    {
        $this->push(TaskListFlow::class);
    }

    #[Action('action:categories')]
    public function onCategories(): void
    {
        $this->push(CategoryManageFlow::class);
    }

    #[Action('action:task_search')]
    public function onSearch(): void
    {
        $this->push(TaskListFlow::class, ['searchMode' => true]);
    }

    #[Action('action:trash')]
    public function onTrash(): void
    {
        $this->push(TrashFlow::class);
    }

    #[Action('action:settings')]
    public function onSettings(): void
    {
        $this->push(SettingsFlow::class);
    }

    #[Action('task:view_{id}')]
    #[Action('task:view:{id}')]
    public function onViewTask(int $id): void
    {
        $callbackMessageId = $this->update->callbackQuery?->message?->messageId;
        if ($callbackMessageId !== null && $this->messageId !== null && $callbackMessageId !== $this->messageId) {
            // Delete the old flow screen above so there are no duplicate or orphan flow menus
            try {
                $this->bot->deleteMessage(chatId: $this->chatId, messageId: $this->messageId);
            } catch (\Throwable) {
            }

            // Keep the reminder notification message intact and send a fresh TaskDetailFlow screen at the bottom
            $this->messageId = null;
            $this->jumpTo(TaskDetailFlow::class, initialData: ['taskId' => $id]);
            return;
        }

        $this->push(TaskDetailFlow::class, ['taskId' => $id]);
    }

    #[Action('task:quick_done_{id}')]
    #[Action('task:quick_done:{id}')]
    public function onQuickDone(int $id): void
    {
        $task = $this->db->tasks()->findForChat($this->chatId, $id);
        if ($task !== null) {
            $task->markCompleted();
            $this->db->tasks()->save($task);
            $this->toast("Task \"{$task->shortTitle}\" marked as completed! ✅");

            $callbackMessageId = $this->update->callbackQuery?->message?->messageId;
            if ($callbackMessageId !== null && $callbackMessageId !== $this->messageId) {
                try {
                    $this->bot->editMessageText(
                        chatId: $this->chatId,
                        messageId: $callbackMessageId,
                        text: "✅ <b>Task Completed!</b>\n\n📌 <b>Title:</b> " . htmlspecialchars($task->title, ENT_QUOTES),
                        parseMode: 'HTML',
                        replyMarkup: null
                    );
                } catch (\Throwable) {
                }
            }
        } else {
            $this->toast("Task not found.");
        }
        $this->refresh();
    }

    #[Action('remind:snooze_{id}_{minutes}')]
    #[Action('remind:snooze:{id}:{minutes}')]
    public function onSnooze(int $id, int $minutes): void
    {
        $res = $this->reminders->snoozeReminder($id, $minutes);
        if ($res !== null) {
            $this->toast("Reminder snoozed for {$minutes} minutes. 💤");

            $callbackMessageId = $this->update->callbackQuery?->message?->messageId;
            if ($callbackMessageId !== null && $callbackMessageId !== $this->messageId) {
                try {
                    $this->bot->editMessageText(
                        chatId: $this->chatId,
                        messageId: $callbackMessageId,
                        text: "💤 <b>Reminder Snoozed!</b> ({$minutes} min)\n\n📌 <b>Task:</b> " . htmlspecialchars($res->task->title, ENT_QUOTES),
                        parseMode: 'HTML',
                        replyMarkup: null
                    );
                } catch (\Throwable) {
                }
            }
        } else {
            $this->toast("Failed to snooze reminder.");
        }
        $this->refresh();
    }
}
