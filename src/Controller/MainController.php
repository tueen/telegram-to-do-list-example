<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Controller;

use DateTimeImmutable;
use DateTimeZone;
use Tueen\Telegram\Routing\Attributes\OnCallbackQuery;
use Tueen\Telegram\Routing\Attributes\OnCommand;
use Tueen\Telegram\Telegram;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Flow\MainMenuFlow;
use Tueen\Telegram\Example\ToDoList\Flow\SettingsFlow;
use Tueen\Telegram\Example\ToDoList\Flow\TaskCreateFlow;
use Tueen\Telegram\Example\ToDoList\Flow\TaskDetailFlow;
use Tueen\Telegram\Example\ToDoList\Flow\TaskListFlow;
use Tueen\Telegram\Example\ToDoList\Flow\TrashFlow;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\ReminderService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;

class MainController
{
    private DatabaseService $db;
    private UserSettingsService $settings;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
        $this->settings = new UserSettingsService($this->db);
    }

    #[OnCommand('start', description: 'Start the bot and open main dashboard', priority: true)]
    public function onStart(Update $update, Telegram $bot): void
    {
        $bot->replaceFlow(MainMenuFlow::class, update: $update);
    }

    #[OnCommand('help', description: 'User guide and commands', priority: true)]
    public function onHelp(Update $update, Telegram $bot): void
    {
        $chatId = $update->findChat()?->id;
        if ($chatId === null) {
            return;
        }

        $text = "📖 <b>Task Manager Bot Guide (To-Do List)</b>\n\n"
            . "This bot helps you organize personal, work, and study tasks seamlessly.\n\n"
            . "✨ <b>Key Features:</b>\n"
            . "▪️ <b>Quick Task Creation:</b> Title, description, deadline, priority, and recurrence\n"
            . "▪️ <b>Smart Categorization:</b> Work, Personal, Education, Programming, and custom categories\n"
            . "▪️ <b>4 Priority Levels:</b> Low 🟢, Medium 🟡, High 🟠, Urgent 🔴\n"
            . "▪️ <b>Deadlines & Reminders:</b> Timely alerts with Snooze support\n"
            . "▪️ <b>Search & Advanced Filters:</b> Filter by status, priority, category, and date\n"
            . "▪️ <b>Trash Bin:</b> Restore deleted tasks or permanently delete them\n"
            . "▪️ <b>Custom Timezones:</b> Synced with your local clock\n\n"
            . "⌨️ <b>Commands:</b>\n"
            . "/start - Open main dashboard\n"
            . "/new - Add a new task\n"
            . "/tasks - View task list\n"
            . "/today - Tasks due today\n"
            . "/trash - View trash bin\n"
            . "/settings - Timezone and notifications\n"
            . "/help - Show this guide";

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    #[OnCommand('new', description: 'Create a new task', priority: true)]
    public function onNewTask(Update $update, Telegram $bot): void
    {
        $bot->replaceFlow(TaskCreateFlow::class, update: $update);
    }

    #[OnCommand('tasks', description: 'View active task list', priority: true)]
    public function onTasks(Update $update, Telegram $bot): void
    {
        $bot->replaceFlow(TaskListFlow::class, update: $update);
    }

    #[OnCommand('trash', description: 'View trash bin', priority: true)]
    public function onTrash(Update $update, Telegram $bot): void
    {
        $bot->replaceFlow(TrashFlow::class, update: $update);
    }

    #[OnCommand('settings', description: 'Timezone and notification settings', priority: true)]
    public function onSettings(Update $update, Telegram $bot): void
    {
        $bot->replaceFlow(SettingsFlow::class, update: $update);
    }

    #[OnCommand('today', description: 'Tasks due today', priority: true)]
    public function onToday(Update $update, Telegram $bot): void
    {
        $chatId = $update->findChat()?->id;
        if ($chatId === null) {
            return;
        }

        $tz = $this->settings->getTimezone($chatId);
        $tasks = $this->db->tasks()->findActiveByChat($chatId, limit: 20);

        $todayDate = (new DateTimeImmutable('now', new DateTimeZone($tz)))->format('Y-m-d');
        $todayTasks = [];

        foreach ($tasks as $task) {
            if ($task->deadline !== null) {
                $taskDate = (DateTimeImmutable::createFromInterface($task->deadline)->setTimezone(new DateTimeZone($tz)))->format('Y-m-d');
                if ($taskDate === $todayDate) {
                    $todayTasks[] = $task;
                }
            }
        }

        if (empty($todayTasks)) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => "☀️ No tasks due today. Have a great day!",
                'parse_mode' => 'HTML',
            ]);
            return;
        }

        $msg = "📅 <b>Tasks Due Today ({$todayDate}):</b>\n\n";
        foreach ($todayTasks as $i => $task) {
            $num = $i + 1;
            $msg .= "{$num}. {$task->statusBadge} <b>" . htmlspecialchars($task->title, ENT_QUOTES) . "</b> ({$task->priorityBadge})\n";
        }

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
        ]);
    }

    #[OnCallbackQuery('task:view_{id}', priority: true)]
    #[OnCallbackQuery('task:view:{id}', priority: true)]
    public function onViewTaskFromCallback(Update $update, Telegram $bot, int $id): void
    {
        if ($update->callbackQuery?->id !== null) {
            try {
                $bot->answerCallbackQuery($update->callbackQuery->id);
            } catch (\Throwable) {
            }
        }

        // replaceFlow finishes and deletes the previous active flow screen (if any)
        // while leaving the reminder notification message intact in chat history.
        $bot->replaceFlow(TaskDetailFlow::class, update: $update, initialData: ['taskId' => $id]);
    }

    #[OnCallbackQuery('task:quick_done_{id}', priority: true)]
    #[OnCallbackQuery('task:quick_done:{id}', priority: true)]
    public function onQuickDoneFromCallback(Update $update, Telegram $bot, int $id): void
    {
        $chatId = $update->findChat()?->id;
        $cbQueryId = $update->callbackQuery?->id;
        $cbMessageId = $update->callbackQuery?->message?->messageId;

        if ($chatId === null) {
            return;
        }

        $task = $this->db->tasks()->findForChat($chatId, $id);
        if ($task !== null) {
            $task->markCompleted();
            $this->db->tasks()->save($task);

            if ($cbQueryId !== null) {
                try {
                    $bot->answerCallbackQuery(callbackQueryId: $cbQueryId, text: "Task \"{$task->shortTitle}\" completed! ✅");
                } catch (\Throwable) {
                }
            }

            if ($cbMessageId !== null) {
                try {
                    $bot->editMessageText(
                        chatId: $chatId,
                        messageId: $cbMessageId,
                        text: "✅ <b>Task Completed!</b>\n\n📌 <b>Title:</b> " . htmlspecialchars($task->title, ENT_QUOTES),
                        parseMode: 'HTML',
                        replyMarkup: null
                    );
                } catch (\Throwable) {
                }
            }
        } else {
            if ($cbQueryId !== null) {
                try {
                    $bot->answerCallbackQuery(callbackQueryId: $cbQueryId, text: "Task not found or already completed.");
                } catch (\Throwable) {
                }
            }
        }
    }

    #[OnCallbackQuery('remind:snooze:{id}:{minutes}', priority: true)]
    #[OnCallbackQuery('#^remind:snooze_(?P<id>\d+)_(?P<minutes>\d+)$#', priority: true)]
    public function onSnoozeFromCallback(Update $update, Telegram $bot, int|string $id, int|string $minutes): void
    {
        $id = (int) $id;
        $minutes = (int) $minutes;
        $chatId = $update->findChat()?->id;
        $cbQueryId = $update->callbackQuery?->id;
        $cbMessageId = $update->callbackQuery?->message?->messageId;

        $reminderService = new ReminderService($this->db, $this->settings);
        $res = $reminderService->snoozeReminder($id, $minutes);

        if ($res !== null) {
            if ($cbQueryId !== null) {
                try {
                    $bot->answerCallbackQuery(callbackQueryId: $cbQueryId, text: "Reminder snoozed for {$minutes}m. 💤");
                } catch (\Throwable) {
                }
            }

            if ($chatId !== null && $cbMessageId !== null) {
                try {
                    $bot->editMessageText(
                        chatId: $chatId,
                        messageId: $cbMessageId,
                        text: "💤 <b>Reminder Snoozed!</b> ({$minutes} min)\n\n📌 <b>Task:</b> " . htmlspecialchars($res->task->title, ENT_QUOTES),
                        parseMode: 'HTML',
                        replyMarkup: null
                    );
                } catch (\Throwable) {
                }
            }
        } else {
            if ($cbQueryId !== null) {
                try {
                    $bot->answerCallbackQuery(callbackQueryId: $cbQueryId, text: "Reminder not found or already handled.");
                } catch (\Throwable) {
                }
            }
        }
    }
}
