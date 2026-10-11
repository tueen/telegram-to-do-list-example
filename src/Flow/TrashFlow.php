<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\Attributes\SingleScreen;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;
use Tueen\Telegram\Example\ToDoList\Support\DateTimeHelper;

#[Breadcrumb('🗑 Trash')]
#[SingleScreen]
class TrashFlow extends InteractiveFlow
{
    private DatabaseService $db;
    private UserSettingsService $settings;
    private const PER_PAGE = 6;

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
        if (!$this->has('current_page')) {
            $this->set('current_page', 1);
        }
    }

    #[\Override]
    public function render(): Screen
    {
        $selectedTaskId = $this->get('selected_trash_id');
        if ($selectedTaskId !== null) {
            return $this->renderTrashItemScreen((int)$selectedTaskId);
        }

        return $this->renderTrashListScreen();
    }

    private function renderTrashListScreen(): Screen
    {
        $page = (int) $this->get('current_page', 1);
        $totalCount = $this->db->tasks()->countTrashByChat($this->chatId);
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
            $this->set('current_page', $page);
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $tasks = $this->db->tasks()->findTrashByChat($this->chatId, limit: self::PER_PAGE, offset: $offset);

        $text = "🗑 <b>Trash Bin (Deleted Tasks)</b>\n\n"
            . "📊 Deleted tasks count: <b>{$totalCount}</b> (Page {$page} of {$totalPages})\n";

        if (empty($tasks)) {
            $text .= "\n<i>Your trash bin is empty!</i>";
        } else {
            $text .= "\nTap on any task to restore or permanently delete it:";
        }

        $screen = Screen::make($text)
            ->parseMode('HTML')
            ->withPagination(
                currentPage: $page,
                totalPages: $totalPages,
                actionPattern: 'trash:page_{page}',
                prevLabel: '◀️ Prev',
                nextLabel: 'Next ▶️',
            );

        $screen->inline(function (InlineKeyboard $k) use ($tasks, $totalCount) {
            foreach ($tasks as $task) {
                $k->row()->action("🗑 {$task->shortTitle}", "trash:select_{$task->id}");
            }

            if ($totalCount > 0) {
                $k->row()->action('🧹 Empty Entire Trash', 'trash:confirm_empty');
            }

            $k->row()
                ->action('🔙 Back', 'flow:back')
                ->action('🏠 Home', 'flow:home');
        });

        return $screen;
    }

    private function renderTrashItemScreen(int $taskId): Screen
    {
        $task = $this->db->tasks()->findForChat($this->chatId, $taskId);
        if ($task === null || !$task->isDeleted) {
            $this->set('selected_trash_id', null);
            return $this->renderTrashListScreen();
        }

        $tz = $this->settings->getTimezone($this->chatId);
        $deletedStr = $task->deletedAt !== null ? DateTimeHelper::formatDateTime($task->deletedAt, $tz) : 'Unknown';

        $text = "🗑 <b>Manage Deleted Task:</b>\n\n"
            . "📌 <b>Title:</b> " . htmlspecialchars($task->title, ENT_QUOTES) . "\n"
            . "🎯 <b>Priority:</b> {$task->priorityBadge}\n"
            . "🗓 <b>Deleted Date:</b> {$deletedStr}\n\n"
            . "What would you like to do?";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k
                ->row()
                ->action('♻️ Restore Task', "trash:restore_{$task->id}")
                ->action('❌ Permanently Delete', "trash:confirm_delete_{$task->id}")
                ->row()
                ->action('🔙 Back to Trash', 'trash:back_to_list')
            );
    }

    #[Action('trash:page_{page}')]
    public function onPageChange(int $page): void
    {
        $this->set('current_page', $page);
        $this->refresh();
    }

    #[Action('trash:select_{id}')]
    public function onSelectTrashItem(int $id): void
    {
        $this->set('selected_trash_id', $id);
        $this->refresh();
    }

    #[Action('trash:back_to_list')]
    public function onBackToList(): void
    {
        $this->set('selected_trash_id', null);
        $this->refresh();
    }

    #[Action('trash:restore_{id}')]
    public function onRestoreTask(int $id): void
    {
        $task = $this->db->tasks()->findForChat($this->chatId, $id);
        if ($task !== null) {
            $task->restore();
            $this->db->tasks()->save($task);
            $this->toast("Task \"{$task->shortTitle}\" restored successfully! ♻️");
        }
        $this->set('selected_trash_id', null);
        $this->refresh();
    }

    #[Action('trash:confirm_delete_{id}')]
    public function onConfirmDelete(int $id): void
    {
        $this->confirm(
            prompt: "⚠️ Are you sure you want to permanently delete this task? This action cannot be undone!",
            onConfirmedAction: "trash:do_delete_{$id}",
            confirmLabel: '❌ Yes, Permanently Delete',
            cancelLabel: '🔙 Cancel',
        );
    }

    #[Action('trash:do_delete_{id}')]
    public function onDoDelete(int $id): void
    {
        $task = $this->db->tasks()->findForChat($this->chatId, $id);
        if ($task !== null) {
            $this->db->tasks()->deletePermanent($task);
            $this->toast("Task permanently deleted 🗑");
        }
        $this->set('selected_trash_id', null);
        $this->refresh();
    }

    #[Action('trash:confirm_empty')]
    public function onConfirmEmpty(): void
    {
        $this->confirm(
            prompt: "⚠️ Are you sure you want to permanently delete all tasks in the trash bin?",
            onConfirmedAction: 'trash:do_empty',
            confirmLabel: '🧹 Yes, Empty Trash',
            cancelLabel: '🔙 Cancel',
        );
    }

    #[Action('trash:do_empty')]
    public function onDoEmpty(): void
    {
        $count = $this->db->tasks()->emptyTrash($this->chatId);
        $this->toast("Trash bin emptied ({$count} tasks purged) 🧹");
        $this->set('selected_trash_id', null);
        $this->refresh();
    }
}
