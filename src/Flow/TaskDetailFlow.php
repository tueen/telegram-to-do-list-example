<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use DateTimeImmutable;
use DateTimeZone;
use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\ReminderService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;
use Tueen\Telegram\Example\ToDoList\Support\DateTimeHelper;

#[Breadcrumb('👁 Task Details')]
class TaskDetailFlow extends InteractiveFlow
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
        if (!$this->has('view_mode')) {
            $this->set('view_mode', 'details');
        }
    }

    private function getActiveTask(): ?Task
    {
        $taskId = (int) $this->get('taskId');
        return $this->db->tasks()->findForChat($this->chatId, $taskId);
    }

    #[\Override]
    public function render(): Screen
    {
        $task = $this->getActiveTask();
        if ($task === null) {
            return Screen::make("❌ Task not found or may have been deleted.")
                ->inline(fn (InlineKeyboard $k) => $k->action('🔙 Back', 'flow:back'));
        }

        $mode = (string) $this->get('view_mode', 'details');

        return match ($mode) {
            'snooze_menu' => $this->renderSnoozeMenuScreen($task),
            default => $this->renderDetailScreen($task),
        };
    }

    private function renderDetailScreen(Task $task): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);

        $catName = $task->category !== null ? $task->category->displayName : '⚪ No Category';
        $deadlineStr = $task->deadline !== null
            ? DateTimeHelper::humanRelative($task->deadline, $tz)
            : 'Not specified';

        $reminderStr = $task->reminderAt !== null
            ? DateTimeHelper::formatDateTime($task->reminderAt, $tz)
            : 'Not specified';

        $createdStr = DateTimeHelper::formatDateTime($task->createdAt, $tz);
        $updatedStr = DateTimeHelper::formatDateTime($task->updatedAt, $tz);

        $text = "📌 <b>Task Details:</b> " . htmlspecialchars($task->title, ENT_QUOTES) . "\n\n";

        if (!empty($task->description)) {
            $text .= "📝 <b>Description:</b>\n" . htmlspecialchars($task->description, ENT_QUOTES) . "\n\n";
        }

        $text .= "📂 <b>Category:</b> {$catName}\n"
            . "🎯 <b>Priority:</b> {$task->priorityBadge}\n"
            . "⏳ <b>Status:</b> {$task->statusBadge}\n"
            . "📅 <b>Deadline:</b> {$deadlineStr}\n";

        if ($task->isOverdue) {
            $text .= "🚨 <b>Alert: This task is overdue!</b>\n";
        }

        $text .= "⏰ <b>Reminder:</b> {$reminderStr}\n"
            . "🔁 <b>Recurrence:</b> {$task->recurrenceBadge}\n"
            . "🗓 <b>Created:</b> {$createdStr}\n"
            . "✏️ <b>Last Updated:</b> {$updatedStr}\n";

        if ($task->completedAt !== null) {
            $text .= "✅ <b>Completed At:</b> " . DateTimeHelper::formatDateTime($task->completedAt, $tz) . "\n";
        }

        $screen = Screen::make($text)->parseMode('HTML');

        $screen->inline(function (InlineKeyboard $k) use ($task) {
            // Status row with checkbox emojis
            $isPending = $task->status === TaskStatus::Pending;
            $isInProgress = $task->status === TaskStatus::InProgress;
            $isCompleted = $task->status === TaskStatus::Completed;

            $k->row()
                ->action(($isPending ? '✅ ' : '☑️ ') . '⏳ Pending', 'status:pending')
                ->action(($isInProgress ? '✅ ' : '☑️ ') . '🔄 In Progress', 'status:in_progress')
                ->row()
                ->action(($isCompleted ? '✅ ' : '☑️ ') . '✅ Completed', 'status:completed');

            // Action row
            $k->row()
                ->action('✏️ Edit Task', 'action:edit')
                ->action('⏰ Reminder / Snooze', 'view:snooze_menu')
                ->row()
                ->action('🗑 Move to Trash', 'action:confirm_trash')
                ->row()
                ->action('🔙 Back to List', 'flow:back')
                ->action('🏠 Home', 'flow:home');
        });

        return $screen;
    }

    private function renderSnoozeMenuScreen(Task $task): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);
        $currentReminder = $task->reminderAt !== null
            ? DateTimeHelper::formatDateTime($task->reminderAt, $tz)
            : 'Not set';

        $text = "⏰ <b>Manage & Snooze Reminder</b>\n\n"
            . "📌 <b>Task:</b> " . htmlspecialchars($task->title, ENT_QUOTES) . "\n"
            . "Current reminder: <b>{$currentReminder}</b>\n\n"
            . "Select an option to snooze or reschedule:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action('✏️ Edit / Reschedule Reminder', 'snooze:edit_reminder')
                ->row()
                ->action('💤 Snooze 15m', 'snooze:min:15')
                ->action('💤 Snooze 30m', 'snooze:min:30')
                ->row()
                ->action('💤 Snooze 1h', 'snooze:min:60')
                ->action('💤 Snooze 2h', 'snooze:min:120')
                ->row()
                ->action('💤 Snooze 1d (Tomorrow same time)', 'snooze:min:1440')
                ->row()
                ->action('⏰ Tomorrow Morning (09:00)', 'snooze:tomorrow_morning')
                ->row()
                ->action('❌ Cancel Reminder', 'snooze:cancel')
                ->row()
                ->action('🔙 Back to Task', 'view:details')
            );
    }

    #[Action('status:pending')]
    public function onSetPending(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $task->markPending();
            $this->db->tasks()->save($task);
            $this->toast("Status set to Pending ⏳");
        }
        $this->refresh();
    }

    #[Action('status:in_progress')]
    public function onSetInProgress(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $task->markInProgress();
            $this->db->tasks()->save($task);
            $this->toast("Status set to In Progress 🔄");
        }
        $this->refresh();
    }

    #[Action('status:completed')]
    public function onSetCompleted(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $task->markCompleted();
            $this->db->tasks()->save($task);

            // Handle recurring task if configured
            if ($task->recurrence->value !== 'none' && $task->deadline !== null) {
                $nextDate = $task->recurrence->calculateNextDate($task->deadline);
                if ($nextDate !== null) {
                    $nextTask = $this->db->tasks()->createTask(
                        chatId: $this->chatId,
                        title: $task->title,
                        description: $task->description,
                        priority: $task->priority,
                        category: $task->category,
                        deadline: $nextDate,
                        recurrence: $task->recurrence,
                    );
                    $this->toast("Task completed! Next occurrence scheduled for " . $nextDate->format('Y-m-d') . " 🔁");
                    $this->refresh();
                    return;
                }
            }

            $this->toast("Task completed! Great job 🎉");
        }
        $this->refresh();
    }

    #[Action('view:snooze_menu')]
    public function onViewSnooze(): void
    {
        $this->set('view_mode', 'snooze_menu');
        $this->refresh();
    }

    #[Action('view:details')]
    public function onViewDetails(): void
    {
        $this->set('view_mode', 'details');
        $this->refresh();
    }

    #[Action('snooze:edit_reminder')]
    public function onEditReminder(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $this->push(TaskEditFlow::class, ['taskId' => $task->id, 'edit_target' => 'reminder']);
        }
    }

    #[Action('snooze:tomorrow_morning')]
    public function onSnoozeTomorrowMorning(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
            $target = (new DateTimeImmutable('tomorrow 09:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
            $task->reminderAt = $target;
            $this->db->tasks()->save($task);

            $this->db->reminders()->deleteForTask($task->id);
            $this->db->reminders()->createReminder($task, $target, ReminderType::Scheduled);

            $this->toast("Reminder scheduled for tomorrow at 09:00 ⏰");
            $this->set('view_mode', 'details');
        }
        $this->refresh();
    }

    #[Action('snooze:cancel')]
    public function onCancelReminder(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $task->reminderAt = null;
            $this->db->tasks()->save($task);
            $this->db->reminders()->deleteForTask($task->id);

            $this->toast("Reminder cancelled.");
            $this->set('view_mode', 'details');
        }
        $this->refresh();
    }

    #[Action('snooze:min:{minutes}')]
    #[Action('snooze:mins:{minutes}')]
    #[Action('snooze:{minutes}')]
    public function onSnooze(int|string $minutes): void
    {
        if (!is_numeric($minutes)) {
            return;
        }
        $minutes = (int) $minutes;
        $task = $this->getActiveTask();
        if ($task !== null) {
            $now = new DateTimeImmutable();
            $target = $now->modify("+{$minutes} minutes");
            $task->reminderAt = $target;
            $this->db->tasks()->save($task);

            $this->db->reminders()->deleteForTask($task->id);
            $this->db->reminders()->createReminder($task, $target, ReminderType::Scheduled);

            $this->toast("Reminder snoozed for {$minutes} minutes 💤");
            $this->set('view_mode', 'details');
        }
        $this->refresh();
    }

    #[Action('action:confirm_trash')]
    public function onConfirmTrash(): void
    {
        $this->confirm(
            prompt: "⚠️ Are you sure you want to move this task to the trash bin?\n(You can restore it later from Trash)",
            onConfirmedAction: 'action:do_trash',
            confirmLabel: '🗑 Yes, Move to Trash',
            cancelLabel: '🔙 Cancel',
        );
    }

    #[Action('action:do_trash')]
    public function onDoTrash(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $task->softDelete();
            $this->db->tasks()->save($task);
            $this->toast("Task moved to trash bin 🗑");
            $this->pop();
            return;
        }
        $this->refresh();
    }

    #[Action('action:edit')]
    public function onEditTask(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $this->push(TaskEditFlow::class, ['taskId' => $task->id]);
        }
    }

    #[\Override]
    public function onUnhandled(\Tueen\Telegram\Types\Update $update): void
    {
        $cbData = $update->callbackQuery?->data ?? '';
        if (str_starts_with($cbData, 'remind:snooze_') || str_starts_with($cbData, 'task:quick_done_') || str_starts_with($cbData, 'task:view_')) {
            $this->passThrough();
            return;
        }

        parent::onUnhandled($update);
    }
}
