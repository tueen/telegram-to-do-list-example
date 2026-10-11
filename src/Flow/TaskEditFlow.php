<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use DateTimeImmutable;
use DateTimeZone;
use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\Attributes\Input;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskRecurrence;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;
use Tueen\Telegram\Example\ToDoList\Support\DateTimeHelper;

#[Breadcrumb('✏️ Edit Task')]
class TaskEditFlow extends InteractiveFlow
{
    private DatabaseService $db;
    private UserSettingsService $settings;

    public function __construct(
        ?DatabaseService $db = null,
        ?UserSettingsService $settings = null,
    ) {
        $this->db = $db ?? DatabaseService::getInstance();
        $this->settings = $settings ?? new UserSettingsService($this->db);
    }

    #[\Override]
    public function onCreate(): void
    {
        if (!$this->has('edit_target')) {
            $this->set('edit_target', 'menu');
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
            return Screen::make("❌ Task not found.")
                ->inline(fn (InlineKeyboard $k) => $k->action('🔙 Back', 'flow:back'));
        }

        $target = (string) $this->get('edit_target', 'menu');

        return match ($target) {
            'title' => $this->renderEditTitlePrompt($task),
            'desc' => $this->renderEditDescPrompt($task),
            'priority' => $this->renderEditPriorityMenu($task),
            'category' => $this->renderEditCategoryMenu($task),
            'deadline' => $this->renderEditDeadlineMenu($task),
            'recurrence' => $this->renderEditRecurrenceMenu($task),
            'reminder' => $this->renderEditReminderMenu($task),
            default => $this->renderMainMenu($task),
        };
    }

    private function renderMainMenu(Task $task): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);
        $dlStr = $task->deadline !== null ? DateTimeHelper::formatDateTime($task->deadline, $tz) : 'Not specified';
        $remStr = $task->reminderAt !== null ? DateTimeHelper::formatDateTime($task->reminderAt, $tz) : 'Not set';

        $text = "✏️ <b>Edit Task Details</b>\n\n"
            . "📌 <b>Title:</b> " . htmlspecialchars($task->title, ENT_QUOTES) . "\n"
            . "🎯 <b>Priority:</b> {$task->priorityBadge}\n"
            . "📂 <b>Category:</b> " . ($task->category ? $task->category->displayName : '⚪ No Category') . "\n"
            . "📅 <b>Deadline:</b> {$dlStr}\n"
            . "⏰ <b>Reminder:</b> {$remStr}\n"
            . "🔁 <b>Recurrence:</b> {$task->recurrenceBadge}\n\n"
            . "Which section would you like to edit?";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action('✏️ Edit Title', 'target:title')
                ->action('📝 Edit Description', 'target:desc')
                ->row()
                ->action('🎯 Change Priority', 'target:priority')
                ->action('📂 Change Category', 'target:category')
                ->row()
                ->action('📅 Change Deadline', 'target:deadline')
                ->action('⏰ Change Reminder', 'target:reminder')
                ->row()
                ->action('🔁 Change Recurrence', 'target:recurrence')
                ->row()
                ->action('🔙 Back to Task Details', 'flow:back')
            );
    }

    private function renderEditTitlePrompt(Task $task): Screen
    {
        $text = "✏️ <b>Edit Task Title</b>\n\n"
            . "Current title: <b>" . htmlspecialchars($task->title, ENT_QUOTES) . "</b>\n\n"
            . "Please send the new title in the chat:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k->action('🔙 Cancel', 'target:menu'));
    }

    private function renderEditDescPrompt(Task $task): Screen
    {
        $desc = $task->description ? htmlspecialchars($task->description, ENT_QUOTES) : '<i>None</i>';
        $text = "📝 <b>Edit Task Description</b>\n\n"
            . "Current description:\n{$desc}\n\n"
            . "Please send the new description text or tap \"Clear Description\":";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->action('🗑 Clear Description', 'desc:clear')
                ->row()
                ->action('🔙 Cancel', 'target:menu')
            );
    }

    private function renderEditPriorityMenu(Task $task): Screen
    {
        $text = "🎯 <b>Change Task Priority</b>\n\n"
            . "Select a new priority level:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action(($task->priority === TaskPriority::Low ? '✅ ' : '☑️ ') . '🟢 Low', 'prio:low')
                ->action(($task->priority === TaskPriority::Medium ? '✅ ' : '☑️ ') . '🟡 Medium', 'prio:medium')
                ->row()
                ->action(($task->priority === TaskPriority::High ? '✅ ' : '☑️ ') . '🟠 High', 'prio:high')
                ->action(($task->priority === TaskPriority::Urgent ? '✅ ' : '☑️ ') . '🔴 Urgent', 'prio:urgent')
                ->row()
                ->action('🔙 Back to Edit Menu', 'target:menu')
            );
    }

    private function renderEditCategoryMenu(Task $task): Screen
    {
        $text = "📂 <b>Change Task Category</b>\n\n"
            . "Select a new category:";

        $categories = $this->db->categories()->findByChat($this->chatId);
        $currentCatId = $task->category?->id;

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($categories, $currentCatId) {
                foreach (array_chunk($categories, 2) as $row) {
                    $k->row();
                    foreach ($row as $cat) {
                        $check = ($currentCatId === $cat->id) ? '✅ ' : '☑️ ';
                        $k->action($check . $cat->displayName, "cat:set_{$cat->id}");
                    }
                }
                $k->row()
                    ->action(($currentCatId === null ? '✅ ' : '☑️ ') . '⚪ No Category', 'cat:set_none')
                    ->row()
                    ->action('🔙 Back to Edit Menu', 'target:menu');
            });
    }

    private function renderEditDeadlineMenu(Task $task): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);
        $dlStr = $task->deadline !== null ? DateTimeHelper::formatDateTime($task->deadline, $tz) : 'Not specified';

        $text = "📅 <b>Change Task Deadline</b>\n\n"
            . "Current deadline: <b>{$dlStr}</b>\n\n"
            . "Select a quick option or send a new date/time in the chat:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action('⚡ Today at 23:59', 'dl:set_today')
                ->row()
                ->action('🌅 Tomorrow at 18:00', 'dl:set_tomorrow')
                ->row()
                ->action('📅 End of Week', 'dl:set_week')
                ->row()
                ->action('❌ Remove Deadline', 'dl:set_none')
                ->row()
                ->action('🔙 Back to Edit Menu', 'target:menu')
            );
    }

    private function renderEditRecurrenceMenu(Task $task): Screen
    {
        $text = "🔁 <b>Change Task Recurrence</b>\n\n"
            . "Select a new recurrence frequency:";

        $cur = $task->recurrence->value;

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action(($cur === 'none' ? '✅ ' : '☑️ ') . '⚪ One-time', 'rec:set_none')
                ->row()
                ->action(($cur === 'daily' ? '✅ ' : '☑️ ') . '🔁 Daily', 'rec:set_daily')
                ->row()
                ->action(($cur === 'weekly' ? '✅ ' : '☑️ ') . '🔁 Weekly', 'rec:set_weekly')
                ->row()
                ->action(($cur === 'monthly' ? '✅ ' : '☑️ ') . '🔁 Monthly', 'rec:set_monthly')
                ->row()
                ->action('🔙 Back to Edit Menu', 'target:menu')
            );
    }

    private function renderEditReminderMenu(Task $task): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);
        $remStr = $task->reminderAt !== null
            ? DateTimeHelper::formatDateTime($task->reminderAt, $tz)
            : 'Not set';

        $text = "⏰ <b>Change Task Reminder</b>\n\n"
            . "📌 <b>Task:</b> " . htmlspecialchars($task->title, ENT_QUOTES) . "\n"
            . "Current reminder: <b>{$remStr}</b>\n\n"
            . "Select a quick preset below or type a custom date & time into the chat\n"
            . "(e.g. <code>tomorrow 09:00</code>, <code>18:30</code>, <code>2026-10-15 14:00</code>):";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($task) {
                if ($task->deadline !== null) {
                    $k->row()
                        ->action('🎯 At Deadline', 'remind:set_at_deadline')
                        ->action('⏰ 1h Before Deadline', 'remind:set_1h_before')
                        ->row()
                        ->action('📅 1d Before Deadline', 'remind:set_1d_before');
                } else {
                    $k->row()
                        ->action('🌅 Tomorrow Morning (09:00)', 'remind:set_tomorrow_morning')
                        ->action('📅 In 2 Days (10:00)', 'remind:set_2d');
                }

                $k->row()
                    ->action('❌ Remove Reminder', 'remind:set_none')
                    ->row()
                    ->action('🔙 Back to Edit Menu', 'target:menu');
            });
    }

    #[Input]
    public function onTextInput(Update $update): void
    {
        $task = $this->getActiveTask();
        if ($task === null) {
            return;
        }

        $target = (string) $this->get('edit_target', 'menu');
        $text = trim($update->findAnyText() ?? '');
        if ($text === '') {
            return;
        }

        if ($target === 'title') {
            $task->title = $text;
            $this->db->tasks()->save($task);
            $this->toast("Task title updated! ✏️");
            $this->set('edit_target', 'menu');
            $this->resendAtBottom();
            return;
        }

        if ($target === 'desc') {
            $task->description = $text;
            $this->db->tasks()->save($task);
            $this->toast("Task description updated! 📝");
            $this->set('edit_target', 'menu');
            $this->resendAtBottom();
            return;
        }

        if ($target === 'deadline') {
            $tz = $this->settings->getTimezone($this->chatId);
            $dt = DateTimeHelper::parseUserDateTime($text, $tz);
            if ($dt === null) {
                $this->bot->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "❌ <b>Invalid date format:</b> \"<code>" . htmlspecialchars($text, ENT_QUOTES) . "</code>\"\n\nPlease send valid date & time like:\n• <code>tomorrow 18:00</code>\n• <code>18:30</code>\n• <code>2026-10-15 14:00</code>\n• <code>+2 days</code>",
                    'parse_mode' => 'HTML',
                ]);
                $this->resendAtBottom();
                return;
            }

            $task->deadline = $dt;
            $this->db->tasks()->save($task);
            $this->toast("Task deadline updated! 📅");
            $this->set('edit_target', 'menu');
            $this->resendAtBottom();
            return;
        }

        if ($target === 'reminder') {
            $tz = $this->settings->getTimezone($this->chatId);
            $dt = DateTimeHelper::parseUserDateTime($text, $tz);
            if ($dt === null) {
                $this->bot->sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "❌ <b>Invalid reminder format:</b> \"<code>" . htmlspecialchars($text, ENT_QUOTES) . "</code>\"\n\nPlease send valid date & time like:\n• <code>tomorrow 09:00</code>\n• <code>18:30</code>\n• <code>2026-10-15 14:00</code>\n• <code>+3 hours</code>",
                    'parse_mode' => 'HTML',
                ]);
                $this->resendAtBottom();
                return;
            }

            $this->applyReminderChange($task, $dt, "Reminder updated to " . DateTimeHelper::formatDateTime($dt, $tz) . " ⏰");
            $this->set('edit_target', 'menu');
            $this->resendAtBottom();
            return;
        }

        $this->refresh();
    }

    #[Action('target:{name}')]
    public function onSetTarget(string $name): void
    {
        $this->set('edit_target', $name);
        $this->refresh();
    }

    #[Action('desc:clear')]
    public function onClearDesc(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $task->description = null;
            $this->db->tasks()->save($task);
            $this->toast("Task description cleared.");
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('prio:{val}')]
    public function onSetPriority(string $val): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $prio = TaskPriority::tryFrom($val);
            if ($prio !== null) {
                $task->priority = $prio;
                $this->db->tasks()->save($task);
                $this->toast("Priority updated to {$prio->label()} 🎯");
            }
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('cat:set_{val}')]
    public function onSetCategory(string $val): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            if ($val === 'none') {
                $task->category = null;
                $this->toast("Category removed.");
            } else {
                $cat = $this->db->categories()->findForChat($this->chatId, (int)$val);
                if ($cat !== null) {
                    $task->category = $cat;
                    $this->toast("Category updated to \"{$cat->displayName}\" 📂");
                }
            }
            $this->db->tasks()->save($task);
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('dl:set_{val}')]
    public function onSetDeadline(string $val): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));

            if ($val === 'none') {
                $task->deadline = null;
                $this->toast("Deadline removed.");
            } elseif ($val === 'today') {
                $task->deadline = (new DateTimeImmutable('today 23:59:00', $tz))->setTimezone(new DateTimeZone('UTC'));
                $this->toast("Deadline set for today 23:59 ⚡");
            } elseif ($val === 'tomorrow') {
                $task->deadline = (new DateTimeImmutable('tomorrow 18:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
                $this->toast("Deadline set for tomorrow 18:00 🌅");
            } elseif ($val === 'week') {
                $task->deadline = (new DateTimeImmutable('+7 days 18:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
                $this->toast("Deadline set for end of week 📅");
            }

            $this->db->tasks()->save($task);
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('rec:set_{val}')]
    public function onSetRecurrence(string $val): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $rec = TaskRecurrence::tryFrom($val);
            if ($rec !== null) {
                $task->recurrence = $rec;
                $this->db->tasks()->save($task);
                $this->toast("Recurrence updated to {$rec->label()} 🔁");
            }
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('remind:set_none')]
    public function onSetRemindNone(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $this->applyReminderChange($task, null, "Reminder removed.");
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('remind:set_at_deadline')]
    public function onSetRemindAtDeadline(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null && $task->deadline !== null) {
            $this->applyReminderChange($task, $task->deadline, "Reminder set for deadline time ⏰");
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('remind:set_1h_before')]
    public function onSetRemind1hBefore(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null && $task->deadline !== null) {
            $dt = $task->deadline->modify('-1 hour');
            $this->applyReminderChange($task, $dt, "Reminder set for 1 hour before deadline ⏰");
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('remind:set_1d_before')]
    public function onSetRemind1dBefore(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null && $task->deadline !== null) {
            $dt = $task->deadline->modify('-1 day');
            $this->applyReminderChange($task, $dt, "Reminder set for 1 day before deadline 📅");
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('remind:set_tomorrow_morning')]
    public function onSetRemindTomorrowMorning(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
            $dt = (new DateTimeImmutable('tomorrow 09:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
            $this->applyReminderChange($task, $dt, "Reminder set for tomorrow 09:00 🌅");
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    #[Action('remind:set_2d')]
    public function onSetRemind2d(): void
    {
        $task = $this->getActiveTask();
        if ($task !== null) {
            $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
            $dt = (new DateTimeImmutable('+2 days 10:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
            $this->applyReminderChange($task, $dt, "Reminder set for 2 days ahead (10:00) 📅");
            $this->set('edit_target', 'menu');
        }
        $this->refresh();
    }

    private function applyReminderChange(Task $task, ?DateTimeImmutable $reminderAt, string $toastMessage): void
    {
        $task->reminderAt = $reminderAt;
        $this->db->tasks()->save($task);

        $this->db->reminders()->deleteForTask($task->id);

        if ($reminderAt !== null) {
            $this->db->reminders()->createReminder($task, $reminderAt, ReminderType::Scheduled);
        }

        $this->toast($toastMessage);
    }
}
