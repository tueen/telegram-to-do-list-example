<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Flow;

use Tueen\Telegram\Flow\Attributes\Action;
use Tueen\Telegram\Flow\Attributes\Breadcrumb;
use Tueen\Telegram\Flow\Attributes\Input;
use Tueen\Telegram\Flow\InteractiveFlow;
use Tueen\Telegram\Flow\Screen;
use Tueen\Telegram\Keyboards\InlineKeyboard;
use Tueen\Telegram\Types\Update;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

#[Breadcrumb('📂 Categories')]
class CategoryManageFlow extends InteractiveFlow
{
    private DatabaseService $db;

    public function __construct(
        ?DatabaseService $db = null,
    ) {
        $this->db = $db ?? DatabaseService::getInstance();
    }

    #[\Override]
    public function onCreate(): void
    {
        if (!$this->has('mode')) {
            $this->set('mode', 'list');
        }
    }

    #[\Override]
    public function render(): Screen
    {
        $mode = (string) $this->get('mode', 'list');

        return match ($mode) {
            'add' => $this->renderAddCategoryScreen(),
            default => $this->renderListScreen(),
        };
    }

    private function renderListScreen(): Screen
    {
        $categories = $this->db->categories()->findByChat($this->chatId);

        $text = "📂 <b>Task Categories Management</b>\n\n"
            . "Your active categories:\n\n";

        foreach ($categories as $cat) {
            $count = $this->db->tasks()->countActiveByChat($this->chatId, categoryId: $cat->id);
            $badge = $cat->isDefault ? '<i>(Default)</i>' : '';
            $text .= "▪️ {$cat->displayName}: <b>{$count}</b> tasks {$badge}\n";
        }

        $screen = Screen::make($text)->parseMode('HTML');

        $screen->inline(fn (InlineKeyboard $k) => $k
            ->action('➕ Add New Category', 'cat:add_mode')
            ->row()
            ->action('🔙 Back', 'flow:back')
            ->action('🏠 Home', 'flow:home')
        );

        return $screen;
    }

    private function renderAddCategoryScreen(): Screen
    {
        $text = "➕ <b>Add New Category</b>\n\n"
            . "Please send the name of the new category in the chat.\n"
            . "You can optionally start with an emoji.\n"
            . "<i>(e.g., 🏋️ Fitness, 📚 Reading, 🛒 Shopping)</i>";

        return Screen::make($text)
            ->parseMode('HTML')
            ->inline(fn (InlineKeyboard $k) => $k->action('🔙 Cancel', 'cat:list_mode'));
    }

    #[Input]
    public function onInput(Update $update): void
    {
        $mode = (string) $this->get('mode', 'list');
        if ($mode === 'add') {
            $text = trim($update->findAnyText() ?? '');
            if ($text !== '') {
                $icon = '📁';
                $name = $text;

                if (preg_match('/^(\X)\s*(.+)$/u', $text, $m)) {
                    if (mb_strlen($m[1]) > 0 && preg_match('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}]/u', $m[1])) {
                        $icon = $m[1];
                        $name = $m[2];
                    }
                }

                $this->db->categories()->createCategory($this->chatId, $name, $icon);
                $this->toast("New category created successfully! 📂");
                $this->set('mode', 'list');
                $this->resendAtBottom();
                return;
            }
        }

        $this->refresh();
    }

    #[Action('cat:add_mode')]
    public function onAddMode(): void
    {
        $this->set('mode', 'add');
        $this->refresh();
    }

    #[Action('cat:list_mode')]
    public function onListMode(): void
    {
        $this->set('mode', 'list');
        $this->refresh();
    }
}
