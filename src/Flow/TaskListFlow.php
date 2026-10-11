<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\Attributes\Input;
use Tueen\Telegram\Flow\Attributes\SingleScreen;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;
use Tueen\Telegram\Example\ToDoList\Service\UserSettingsService;

#[Breadcrumb('📋 Task List')]
#[SingleScreen]
class TaskListFlow extends InteractiveFlow
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

        if (!$this->has('view_mode')) {
            $initialMode = (bool)$this->get('searchMode', false) ? 'search' : 'list';
            $this->set('view_mode', $initialMode);
        }
    }

    #[\Override]
    public function render(): Screen
    {
        $mode = (string) $this->get('view_mode', 'list');

        return match ($mode) {
            'filter_status' => $this->renderStatusFilterScreen(),
            'filter_priority' => $this->renderPriorityFilterScreen(),
            'filter_category' => $this->renderCategoryFilterScreen(),
            'sort_menu' => $this->renderSortMenuScreen(),
            'search' => $this->renderSearchScreen(),
            default => $this->renderListScreen(),
        };
    }

    private function renderListScreen(): Screen
    {
        $page = (int) $this->get('current_page', 1);
        $status = $this->settings->getFilterStatus($this->chatId);
        $priority = $this->settings->getFilterPriority($this->chatId);
        $categoryId = $this->settings->getFilterCategory($this->chatId);
        $sort = $this->settings->getSortPreference($this->chatId);
        $search = $this->get('search_query');

        $totalCount = $this->db->tasks()->countActiveByChat(
            chatId: $this->chatId,
            status: $status,
            priority: $priority,
            categoryId: $categoryId,
            search: $search !== null ? (string)$search : null,
        );

        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
            $this->set('current_page', $page);
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $tasks = $this->db->tasks()->findActiveByChat(
            chatId: $this->chatId,
            status: $status,
            priority: $priority,
            categoryId: $categoryId,
            search: $search !== null ? (string)$search : null,
            sort: $sort,
            limit: self::PER_PAGE,
            offset: $offset,
        );

        // Header info
        $filtersApplied = [];
        if ($status !== null) {
            $filtersApplied[] = "Status: " . $status->label();
        }
        if ($priority !== null) {
            $filtersApplied[] = "Priority: " . $priority->label();
        }
        if ($categoryId !== null) {
            $cat = $this->db->categories()->findForChat($this->chatId, $categoryId);
            $filtersApplied[] = "Category: " . ($cat ? $cat->displayName : 'Unknown');
        }
        if ($search !== null && trim((string)$search) !== '') {
            $filtersApplied[] = "Search: \"" . htmlspecialchars((string)$search, ENT_QUOTES) . "\"";
        }

        $filterBadge = !empty($filtersApplied) ? implode(' | ', $filtersApplied) : 'All Tasks';
        $sortBadge = match ($sort) {
            'oldest' => 'Oldest',
            'deadline' => 'Deadline',
            'priority' => 'Highest Priority',
            default => 'Newest',
        };

        $text = "📋 <b>My Tasks List</b>\n\n"
            . "🎯 <b>Active Filter:</b> {$filterBadge}\n"
            . "🔃 <b>Sorting:</b> {$sortBadge}\n"
            . "📊 <b>Total:</b> {$totalCount} tasks (Page {$page} of {$totalPages})\n";

        if (empty($tasks)) {
            $text .= "\n<i>No tasks found matching these criteria!</i>\n"
                . "Tap \"➕ Add Task\" to create a new task.";
        } else {
            $text .= "\nTap on any task to view or manage it:";
        }

        $screen = Screen::make($text)
            ->parseMode('HTML')
            ->withPagination(
                currentPage: $page,
                totalPages: $totalPages,
                actionPattern: 'list:page_{page}',
                prevLabel: '◀️ Prev',
                nextLabel: 'Next ▶️',
            );

        $screen->inline(function (InlineKeyboard $k) use ($tasks, $filtersApplied) {
            foreach ($tasks as $task) {
                $icon = match ($task->status) {
                    TaskStatus::Completed => '✅',
                    TaskStatus::InProgress => '🔄',
                    TaskStatus::Cancelled => '❌',
                    default => '⏳',
                };
                $prioIcon = match ($task->priority) {
                    TaskPriority::Urgent => '🔴',
                    TaskPriority::High => '🟠',
                    TaskPriority::Medium => '🟡',
                    default => '🟢',
                };

                $btnLabel = "{$icon} {$task->shortTitle} ({$prioIcon})";
                $k->row()->action($btnLabel, "task:view_{$task->id}");
            }

            // Filter tools
            $k->row()
                ->action('🔍 Search', 'view:search')
                ->action('🏷 Filter Status', 'view:filter_status')
                ->row()
                ->action('🎯 Filter Priority', 'view:filter_priority')
                ->action('📂 Filter Category', 'view:filter_category')
                ->row()
                ->action('🔃 Sort By', 'view:sort_menu');

            if (!empty($filtersApplied)) {
                $k->action('🧹 Clear Filters', 'filter:clear');
            }

            $k->row()
                ->action('➕ Add Task', 'action:task_new')
                ->action('🏠 Home', 'flow:home');
        });

        return $screen;
    }

    private function renderStatusFilterScreen(): Screen
    {
        $current = $this->settings->getFilterStatus($this->chatId);
        $text = "🏷 <b>Filter by Task Status:</b>\n\n"
            . "Select a status to filter the task list:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($current) {
                $k->row()->action(($current === null ? '✅ ' : '☑️ ') . 'All Statuses', 'set_status:all');

                $k->row()
                    ->action(($current === TaskStatus::Pending ? '✅ ' : '☑️ ') . '⏳ Pending', 'set_status:pending')
                    ->action(($current === TaskStatus::InProgress ? '✅ ' : '☑️ ') . '🔄 In Progress', 'set_status:in_progress');

                $k->row()
                    ->action(($current === TaskStatus::Completed ? '✅ ' : '☑️ ') . '✅ Completed', 'set_status:completed')
                    ->action(($current === TaskStatus::Cancelled ? '✅ ' : '☑️ ') . '❌ Cancelled', 'set_status:cancelled');

                $k->row()->action('🔙 Back to List', 'view:list');
            });
    }

    private function renderPriorityFilterScreen(): Screen
    {
        $current = $this->settings->getFilterPriority($this->chatId);
        $text = "🎯 <b>Filter by Task Priority:</b>\n\n"
            . "Select a priority level to filter:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($current) {
                $k->row()->action(($current === null ? '✅ ' : '☑️ ') . 'All Priorities', 'set_prio:all');

                $k->row()
                    ->action(($current === TaskPriority::Low ? '✅ ' : '☑️ ') . '🟢 Low', 'set_prio:low')
                    ->action(($current === TaskPriority::Medium ? '✅ ' : '☑️ ') . '🟡 Medium', 'set_prio:medium');

                $k->row()
                    ->action(($current === TaskPriority::High ? '✅ ' : '☑️ ') . '🟠 High', 'set_prio:high')
                    ->action(($current === TaskPriority::Urgent ? '✅ ' : '☑️ ') . '🔴 Urgent', 'set_prio:urgent');

                $k->row()->action('🔙 Back to List', 'view:list');
            });
    }

    private function renderCategoryFilterScreen(): Screen
    {
        $current = $this->settings->getFilterCategory($this->chatId);
        $text = "📂 <b>Filter by Task Category:</b>\n\n"
            . "Select a category to filter:";

        $categories = $this->db->categories()->findByChat($this->chatId);

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($categories, $current) {
                $k->row()->action(($current === null ? '✅ ' : '☑️ ') . 'All Categories', 'set_cat:all');

                foreach (array_chunk($categories, 2) as $row) {
                    $k->row();
                    foreach ($row as $cat) {
                        $check = ($current === $cat->id) ? '✅ ' : '☑️ ';
                        $k->action($check . $cat->displayName, "set_cat:{$cat->id}");
                    }
                }

                $k->row()->action('🔙 Back to List', 'view:list');
            });
    }

    private function renderSortMenuScreen(): Screen
    {
        $current = $this->settings->getSortPreference($this->chatId);
        $text = "🔃 <b>Sort Tasks:</b>\n\n"
            . "Choose how you want tasks ordered:";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($current) {
                $k->row()
                    ->action(($current === 'newest' ? '✅ ' : '☑️ ') . 'Newest Creation Date', 'set_sort:newest')
                    ->row()
                    ->action(($current === 'oldest' ? '✅ ' : '☑️ ') . 'Oldest Creation Date', 'set_sort:oldest')
                    ->row()
                    ->action(($current === 'deadline' ? '✅ ' : '☑️ ') . 'Nearest Deadline', 'set_sort:deadline')
                    ->row()
                    ->action(($current === 'priority' ? '✅ ' : '☑️ ') . 'Highest Priority', 'set_sort:priority')
                    ->row()
                    ->action('🔙 Back to List', 'view:list');
            });
    }

    private function renderSearchScreen(): Screen
    {
        $query = $this->get('search_query');
        $queryText = $query ? htmlspecialchars((string)$query, ENT_QUOTES) : '<i>No query</i>';

        $text = "🔍 <b>Search Tasks</b>\n\n"
            . "Type and send your keyword in the chat.\n"
            . "The search will match both task titles and descriptions.\n\n"
            . "🔎 Current query: {$queryText}";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(function (InlineKeyboard $k) use ($query) {
                if ($query !== null) {
                    $k->action('🧹 Clear Search', 'search:clear');
                }
                $k->row()->action('🔙 Back to List', 'view:list');
            });
    }

    #[Input]
    public function onSearchInput(Update $update): void
    {
        $mode = (string) $this->get('view_mode', 'list');
        if ($mode === 'search') {
            $text = trim($update->findAnyText() ?? '');
            if ($text !== '') {
                $this->set('search_query', $text);
                $this->set('view_mode', 'list');
                $this->set('current_page', 1);
                $this->resendAtBottom();
                return;
            }
        }

        $this->refresh();
    }

    #[Action('view:list')]
    public function onViewList(): void
    {
        $this->set('view_mode', 'list');
        $this->refresh();
    }

    #[Action('view:filter_status')]
    public function onViewFilterStatus(): void
    {
        $this->set('view_mode', 'filter_status');
        $this->refresh();
    }

    #[Action('view:filter_priority')]
    public function onViewFilterPriority(): void
    {
        $this->set('view_mode', 'filter_priority');
        $this->refresh();
    }

    #[Action('view:filter_category')]
    public function onViewFilterCategory(): void
    {
        $this->set('view_mode', 'filter_category');
        $this->refresh();
    }

    #[Action('view:sort_menu')]
    public function onViewSortMenu(): void
    {
        $this->set('view_mode', 'sort_menu');
        $this->refresh();
    }

    #[Action('view:search')]
    public function onViewSearch(): void
    {
        $this->set('view_mode', 'search');
        $this->refresh();
    }

    #[Action('search:clear')]
    public function onClearSearch(): void
    {
        $this->set('search_query', null);
        $this->set('view_mode', 'list');
        $this->set('current_page', 1);
        $this->refresh();
    }

    #[Action('filter:clear')]
    public function onClearAllFilters(): void
    {
        $this->settings->clearFilters($this->chatId);
        $this->set('search_query', null);
        $this->set('current_page', 1);
        $this->toast("All filters cleared 🧹");
        $this->refresh();
    }

    #[Action('set_status:{val}')]
    public function onSetStatus(string $val): void
    {
        $status = ($val === 'all') ? null : TaskStatus::tryFrom($val);
        $this->settings->setFilterStatus($this->chatId, $status);
        $this->set('view_mode', 'list');
        $this->set('current_page', 1);
        $this->refresh();
    }

    #[Action('set_prio:{val}')]
    public function onSetPriority(string $val): void
    {
        $priority = ($val === 'all') ? null : TaskPriority::tryFrom($val);
        $this->settings->setFilterPriority($this->chatId, $priority);
        $this->set('view_mode', 'list');
        $this->set('current_page', 1);
        $this->refresh();
    }

    #[Action('set_cat:{val}')]
    public function onSetCategory(string $val): void
    {
        $catId = ($val === 'all') ? null : (int)$val;
        $this->settings->setFilterCategory($this->chatId, $catId);
        $this->set('view_mode', 'list');
        $this->set('current_page', 1);
        $this->refresh();
    }

    #[Action('set_sort:{val}')]
    public function onSetSort(string $val): void
    {
        $this->settings->setSortPreference($this->chatId, $val);
        $this->set('view_mode', 'list');
        $this->set('current_page', 1);
        $this->refresh();
    }

    #[Action('list:page_{page}')]
    public function onPageChange(int $page): void
    {
        $this->set('current_page', $page);
        $this->refresh();
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

    #[Action('action:task_new')]
    public function onTaskNew(): void
    {
        $this->push(TaskCreateFlow::class);
    }

    #[\Override]
    public function onUnhandled(\Tueen\Telegram\Types\Update $update): void
    {
        $cbData = $update->callbackQuery?->data ?? '';
        if (str_starts_with($cbData, 'remind:snooze_') || str_starts_with($cbData, 'task:quick_done_')) {
            $this->passThrough();
            return;
        }

        parent::onUnhandled($update);
    }
}
