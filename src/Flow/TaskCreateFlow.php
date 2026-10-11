<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use DateTimeImmutable;
use DateTimeZone;
use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\Attributes\Input;
use Tueen\Telegram\Flow\Attributes\SingleScreen;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskRecurrence;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;
use Tueen\Telegram\Example\ToDoList\Support\DateTimeHelper;

#[Breadcrumb('➕ Add Task')]
#[SingleScreen]
class TaskCreateFlow extends InteractiveFlow
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
        if (!$this->has('wizard_step')) {
            $this->set('wizard_step', 'title');
        }
    }

    #[\Override]
    public function render(): Screen
    {
        $step = (string) $this->get('wizard_step', 'title');

        return match ($step) {
            'description' => $this->renderDescriptionStep(),
            'category' => $this->renderCategoryStep(),
            'priority' => $this->renderPriorityStep(),
            'deadline' => $this->renderDeadlineStep(),
            'custom_deadline' => $this->renderCustomDeadlineStep(),
            'reminder' => $this->renderReminderStep(),
            'recurrence' => $this->renderRecurrenceStep(),
            'review' => $this->renderReviewStep(),
            default => $this->renderTitleStep(),
        };
    }

    private function renderTitleStep(): Screen
    {
        $text = "📝 <b>Step 1 of 7: Task Title</b>\n\n"
            . "Please type and send the task title in the chat:\n"
            . "<i>(e.g., Read chapter 3, Buy groceries, Team meeting tomorrow)</i>";

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(1, 7, style: 'dots')
            ->inline(fn (InlineKeyboard $k) => $k
                ->action('❌ Cancel & Exit', 'create:cancel')
            );
    }

    private function renderDescriptionStep(): Screen
    {
        $title = htmlspecialchars((string)$this->get('task_title'), ENT_QUOTES);
        $text = "📝 <b>Step 2 of 7: Task Description</b>\n\n"
            . "📌 <b>Title:</b> {$title}\n\n"
            . "If this task has additional details or notes, send the text in the chat;\n"
            . "otherwise tap \"⏭ Skip Description\".";

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(2, 7, style: 'dots')
            ->inline(fn (InlineKeyboard $k) => $k
                ->action('⏭ Skip Description', 'step:skip_desc')
                ->row()
                ->action('🔙 Previous Step', 'step:back_to_title')
                ->action('❌ Cancel', 'create:cancel')
            );
    }

    private function renderCategoryStep(): Screen
    {
        $text = "📂 <b>Step 3 of 7: Select Category</b>\n\n"
            . "Please select a category for this task:";

        $categories = $this->db->categories()->findByChat($this->chatId);
        $selectedCatId = $this->get('task_category_id');

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(3, 7, style: 'dots')
            ->inline(function (InlineKeyboard $k) use ($categories, $selectedCatId) {
                foreach (array_chunk($categories, 2) as $row) {
                    $k->row();
                    foreach ($row as $cat) {
                        $check = ((int)$selectedCatId === $cat->id) ? '✅ ' : '☑️ ';
                        $k->action($check . $cat->displayName, "cat:pick_{$cat->id}");
                    }
                }
                $k->row()
                    ->action('⚪ No Category', 'cat:pick_none')
                    ->row()
                    ->action('🔙 Previous Step', 'step:back_to_desc')
                    ->action('❌ Cancel', 'create:cancel');
            });
    }

    private function renderPriorityStep(): Screen
    {
        $text = "🎯 <b>Step 4 of 7: Set Priority</b>\n\n"
            . "Select the priority level for this task:";

        $selectedPriority = (string)$this->get('task_priority', TaskPriority::Medium->value);

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(4, 7, style: 'dots')
            ->inline(function (InlineKeyboard $k) use ($selectedPriority) {
                $k->row()
                    ->action(($selectedPriority === 'low' ? '✅ ' : '☑️ ') . '🟢 Low', 'prio:pick_low')
                    ->action(($selectedPriority === 'medium' ? '✅ ' : '☑️ ') . '🟡 Medium', 'prio:pick_medium')
                    ->row()
                    ->action(($selectedPriority === 'high' ? '✅ ' : '☑️ ') . '🟠 High', 'prio:pick_high')
                    ->action(($selectedPriority === 'urgent' ? '✅ ' : '☑️ ') . '🔴 Urgent', 'prio:pick_urgent')
                    ->row()
                    ->action('🔙 Previous Step', 'step:back_to_cat')
                    ->action('❌ Cancel', 'create:cancel');
            });
    }

    private function renderDeadlineStep(): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);
        $text = "📅 <b>Step 5 of 7: Set Deadline</b>\n\n"
            . "Do you have a due date or time for this task?\n"
            . "<i>Current timezone: {$tz}</i>";

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(5, 7, style: 'dots')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action('⚡ Today at 23:59', 'dl:today')
                ->row()
                ->action('🌅 Tomorrow at 18:00', 'dl:tomorrow')
                ->row()
                ->action('📅 End of Week (+7 days)', 'dl:week')
                ->row()
                ->action('✏️ Custom Date & Time', 'dl:custom')
                ->row()
                ->action('⏭ No Deadline', 'dl:none')
                ->row()
                ->action('🔙 Previous Step', 'step:back_to_prio')
                ->action('❌ Cancel', 'create:cancel')
            );
    }

    private function renderCustomDeadlineStep(): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);
        $text = "✏️ <b>Enter Custom Date & Time</b>\n\n"
            . "Please send the date or time in one of the following formats:\n"
            . "▪️ <code>2026-10-15 14:00</code> (ISO date and time)\n"
            . "▪️ <code>18:30</code> (time today/tomorrow)\n"
            . "▪️ <code>+2 days</code> or <code>+3 hours</code>\n"
            . "▪️ <code>tomorrow</code> or <code>today</code>\n\n"
            . "<i>Timezone: {$tz}</i>";

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(5, 7, style: 'dots')
            ->inline(fn (InlineKeyboard $k) => $k
                ->action('🔙 Back to Deadline Menu', 'dl:back_menu')
                ->action('❌ Cancel', 'create:cancel')
            );
    }

    private function renderReminderStep(): Screen
    {
        $hasDeadline = $this->has('task_deadline');
        $text = "⏰ <b>Step 6 of 7: Schedule Reminder</b>\n\n"
            . "When should the bot remind you about this task?";

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(6, 7, style: 'dots')
            ->inline(function (InlineKeyboard $k) use ($hasDeadline) {
                if ($hasDeadline) {
                    $k->row()
                        ->action('⏰ Exactly at Deadline', 'remind:at_deadline')
                        ->row()
                        ->action('⏰ 1 hour before', 'remind:1h_before')
                        ->row()
                        ->action('⏰ 1 day before', 'remind:1d_before');
                } else {
                    $k->row()
                        ->action('⏰ Tomorrow morning (09:00)', 'remind:tomorrow_morning')
                        ->row()
                        ->action('⏰ In 2 days', 'remind:2d');
                }
                $k->row()
                    ->action('⏭ No Reminder', 'remind:none')
                    ->row()
                    ->action('🔙 Previous Step', 'step:back_to_dl')
                    ->action('❌ Cancel', 'create:cancel');
            });
    }

    private function renderRecurrenceStep(): Screen
    {
        $text = "🔁 <b>Step 7 of 7: Task Recurrence</b>\n\n"
            . "Is this a recurring task?";

        $selected = (string)$this->get('task_recurrence', TaskRecurrence::None->value);

        return Screen::make($text)
            ->parseMode('HTML')
            ->withStepper(7, 7, style: 'dots')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action(($selected === 'none' ? '✅ ' : '☑️ ') . '⚪ One-time (No recurrence)', 'rec:pick_none')
                ->row()
                ->action(($selected === 'daily' ? '✅ ' : '☑️ ') . '🔁 Daily', 'rec:pick_daily')
                ->row()
                ->action(($selected === 'weekly' ? '✅ ' : '☑️ ') . '🔁 Weekly', 'rec:pick_weekly')
                ->row()
                ->action(($selected === 'monthly' ? '✅ ' : '☑️ ') . '🔁 Monthly', 'rec:pick_monthly')
                ->row()
                ->action('🔙 Previous Step', 'step:back_to_remind')
                ->action('❌ Cancel', 'create:cancel')
            );
    }

    private function renderReviewStep(): Screen
    {
        $tz = $this->settings->getTimezone($this->chatId);
        $title = htmlspecialchars((string)$this->get('task_title'), ENT_QUOTES);
        $desc = $this->get('task_description');
        $descStr = $desc ? htmlspecialchars((string)$desc, ENT_QUOTES) : '<i>None</i>';

        $catId = $this->get('task_category_id');
        $catName = '⚪ No Category';
        if ($catId !== null) {
            $cat = $this->db->categories()->findForChat($this->chatId, (int)$catId);
            if ($cat !== null) {
                $catName = $cat->displayName;
            }
        }

        $prio = TaskPriority::from((string)$this->get('task_priority', 'medium'));
        $rec = TaskRecurrence::from((string)$this->get('task_recurrence', 'none'));

        $deadlineIso = $this->get('task_deadline');
        $deadlineStr = $deadlineIso !== null
            ? DateTimeHelper::formatDateTime(new DateTimeImmutable((string)$deadlineIso), $tz)
            : 'Not specified';

        $reminderIso = $this->get('task_reminder_at');
        $reminderStr = $reminderIso !== null
            ? DateTimeHelper::formatDateTime(new DateTimeImmutable((string)$reminderIso), $tz)
            : 'Not specified';

        $text = "🎉 <b>Review & Confirm New Task</b>\n\n"
            . "📌 <b>Title:</b> {$title}\n"
            . "📝 <b>Description:</b> {$descStr}\n"
            . "📂 <b>Category:</b> {$catName}\n"
            . "🎯 <b>Priority:</b> {$prio->badge()}\n"
            . "📅 <b>Deadline:</b> {$deadlineStr}\n"
            . "⏰ <b>Reminder:</b> {$reminderStr}\n"
            . "🔁 <b>Recurrence:</b> {$rec->badge()}\n\n"
            . "Do you confirm these details?";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->action('✅ Save Task', 'create:save')
                ->row()
                ->action('🔄 Start Over', 'create:restart')
                ->action('❌ Cancel', 'create:cancel')
            );
    }

    #[Input]
    public function onTextInput(Update $update): void
    {
        $text = trim($update->findAnyText() ?? '');
        if ($text === '') {
            return;
        }

        $step = (string) $this->get('wizard_step', 'title');

        if ($step === 'title') {
            $this->set('task_title', $text);
            $this->set('wizard_step', 'description');
            $this->resendAtBottom();
            return;
        }

        if ($step === 'description') {
            $this->set('task_description', $text);
            $this->set('wizard_step', 'category');
            $this->resendAtBottom();
            return;
        }

        if ($step === 'custom_deadline') {
            $tz = $this->settings->getTimezone($this->chatId);
            $dt = DateTimeHelper::parseUserDateTime($text, $tz);
            if ($dt === null) {
                $this->alert("❌ Invalid date format. Please send again according to the pattern.");
                return;
            }

            $this->set('task_deadline', $dt->format('c'));
            $this->set('wizard_step', 'reminder');
            $this->resendAtBottom();
            return;
        }

        $this->refresh();
    }

    #[Action('step:skip_desc')]
    public function onSkipDesc(): void
    {
        $this->set('task_description', null);
        $this->set('wizard_step', 'category');
        $this->refresh();
    }

    #[Action('step:back_to_title')]
    public function onBackToTitle(): void
    {
        $this->set('wizard_step', 'title');
        $this->refresh();
    }

    #[Action('step:back_to_desc')]
    public function onBackToDesc(): void
    {
        $this->set('wizard_step', 'description');
        $this->refresh();
    }

    #[Action('cat:pick_{id}')]
    public function onPickCategory(int $id): void
    {
        $this->set('task_category_id', $id);
        $this->set('wizard_step', 'priority');
        $this->refresh();
    }

    #[Action('cat:pick_none')]
    public function onPickNoCategory(): void
    {
        $this->set('task_category_id', null);
        $this->set('wizard_step', 'priority');
        $this->refresh();
    }

    #[Action('step:back_to_cat')]
    public function onBackToCat(): void
    {
        $this->set('wizard_step', 'category');
        $this->refresh();
    }

    #[Action('prio:pick_{prio}')]
    public function onPickPriority(string $prio): void
    {
        $this->set('task_priority', $prio);
        $this->set('wizard_step', 'deadline');
        $this->refresh();
    }

    #[Action('step:back_to_prio')]
    public function onBackToPrio(): void
    {
        $this->set('wizard_step', 'priority');
        $this->refresh();
    }

    #[Action('dl:today')]
    public function onDeadlineToday(): void
    {
        $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
        $dt = (new DateTimeImmutable('today 23:59:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $this->set('task_deadline', $dt->format('c'));
        $this->set('wizard_step', 'reminder');
        $this->refresh();
    }

    #[Action('dl:tomorrow')]
    public function onDeadlineTomorrow(): void
    {
        $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
        $dt = (new DateTimeImmutable('tomorrow 18:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $this->set('task_deadline', $dt->format('c'));
        $this->set('wizard_step', 'reminder');
        $this->refresh();
    }

    #[Action('dl:week')]
    public function onDeadlineWeek(): void
    {
        $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
        $dt = (new DateTimeImmutable('+7 days 18:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $this->set('task_deadline', $dt->format('c'));
        $this->set('wizard_step', 'reminder');
        $this->refresh();
    }

    #[Action('dl:custom')]
    public function onDeadlineCustom(): void
    {
        $this->set('wizard_step', 'custom_deadline');
        $this->refresh();
    }

    #[Action('dl:back_menu')]
    public function onDeadlineBackMenu(): void
    {
        $this->set('wizard_step', 'deadline');
        $this->refresh();
    }

    #[Action('dl:none')]
    public function onDeadlineNone(): void
    {
        $this->set('task_deadline', null);
        $this->set('wizard_step', 'reminder');
        $this->refresh();
    }

    #[Action('step:back_to_dl')]
    public function onBackToDeadline(): void
    {
        $this->set('wizard_step', 'deadline');
        $this->refresh();
    }

    #[Action('remind:at_deadline')]
    public function onRemindAtDeadline(): void
    {
        $this->set('task_reminder_at', $this->get('task_deadline'));
        $this->set('wizard_step', 'recurrence');
        $this->refresh();
    }

    #[Action('remind:1h_before')]
    public function onRemind1hBefore(): void
    {
        $dl = $this->get('task_deadline');
        if ($dl !== null) {
            $dt = (new DateTimeImmutable((string)$dl))->modify('-1 hour');
            $this->set('task_reminder_at', $dt->format('c'));
        }
        $this->set('wizard_step', 'recurrence');
        $this->refresh();
    }

    #[Action('remind:1d_before')]
    public function onRemind1dBefore(): void
    {
        $dl = $this->get('task_deadline');
        if ($dl !== null) {
            $dt = (new DateTimeImmutable((string)$dl))->modify('-1 day');
            $this->set('task_reminder_at', $dt->format('c'));
        }
        $this->set('wizard_step', 'recurrence');
        $this->refresh();
    }

    #[Action('remind:tomorrow_morning')]
    public function onRemindTomorrowMorning(): void
    {
        $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
        $dt = (new DateTimeImmutable('tomorrow 09:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $this->set('task_reminder_at', $dt->format('c'));
        $this->set('wizard_step', 'recurrence');
        $this->refresh();
    }

    #[Action('remind:2d')]
    public function onRemind2d(): void
    {
        $tz = new DateTimeZone($this->settings->getTimezone($this->chatId));
        $dt = (new DateTimeImmutable('+2 days 10:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $this->set('task_reminder_at', $dt->format('c'));
        $this->set('wizard_step', 'recurrence');
        $this->refresh();
    }

    #[Action('remind:none')]
    public function onRemindNone(): void
    {
        $this->set('task_reminder_at', null);
        $this->set('wizard_step', 'recurrence');
        $this->refresh();
    }

    #[Action('step:back_to_remind')]
    public function onBackToReminder(): void
    {
        $this->set('wizard_step', 'reminder');
        $this->refresh();
    }

    #[Action('rec:pick_{rec}')]
    public function onPickRecurrence(string $rec): void
    {
        $this->set('task_recurrence', $rec);
        $this->set('wizard_step', 'review');
        $this->refresh();
    }

    #[Action('create:restart')]
    public function onRestart(): void
    {
        $this->set('wizard_step', 'title');
        $this->refresh();
    }

    #[Action('create:cancel')]
    public function onCancelCreation(): void
    {
        $this->toast('Task creation cancelled.');
        $this->clearData();

        if (!empty($this->state->flowStack)) {
            $this->pop();
            return;
        }

        $this->home();
    }

    #[Action('create:save')]
    public function onSaveTask(): void
    {
        $title = (string)$this->get('task_title');
        $desc = $this->get('task_description');
        $catId = $this->get('task_category_id');
        $prio = TaskPriority::from((string)$this->get('task_priority', 'medium'));
        $rec = TaskRecurrence::from((string)$this->get('task_recurrence', 'none'));

        $deadline = null;
        if ($this->has('task_deadline')) {
            $deadline = new DateTimeImmutable((string)$this->get('task_deadline'));
        }

        $reminderAt = null;
        if ($this->has('task_reminder_at')) {
            $reminderAt = new DateTimeImmutable((string)$this->get('task_reminder_at'));
        }

        $category = null;
        if ($catId !== null) {
            $category = $this->db->categories()->findForChat($this->chatId, (int)$catId);
        }

        $task = $this->db->tasks()->createTask(
            chatId: $this->chatId,
            title: $title,
            description: $desc !== null ? (string)$desc : null,
            priority: $prio,
            category: $category,
            deadline: $deadline,
            reminderAt: $reminderAt,
            recurrence: $rec,
        );

        if ($reminderAt !== null) {
            $this->db->reminders()->createReminder($task, $reminderAt, ReminderType::Scheduled);
        }

        $this->toast("Task \"{$task->shortTitle}\" created successfully! 🎉");
        $this->clearData();

        if (!empty($this->state->flowStack)) {
            $this->pop(['createdTaskId' => $task->id]);
            return;
        }

        $this->home();
    }
}
