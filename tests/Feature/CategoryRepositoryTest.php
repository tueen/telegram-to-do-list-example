<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Service\DatabaseService;

class CategoryRepositoryTest extends TestCase
{
    private DatabaseService $db;
    private int $testChatId = 12345678;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = DatabaseService::getInstance();
        $this->db->syncSchema();
    }

    public function testSeedDefaultsAndFindByChat(): void
    {
        $categories = $this->db->categories()->findByChat($this->testChatId);

        $this->assertCount(4, $categories);
        $names = array_map(fn($c) => $c->name, $categories);
        $this->assertContains('Work', $names);
        $this->assertContains('Personal', $names);
        $this->assertContains('Education', $names);
        $this->assertContains('Programming', $names);
    }

    public function testCreateCustomCategoryAndFindForChat(): void
    {
        $cat = $this->db->categories()->createCategory(
            chatId: $this->testChatId,
            name: 'Fitness & Health',
            icon: '🏋️',
            color: '#ef4444',
        );

        $this->assertNotNull($cat->id);
        $this->assertSame('Fitness & Health', $cat->name);
        $this->assertSame('🏋️', $cat->icon);
        $this->assertFalse($cat->isDefault);

        $found = $this->db->categories()->findForChat($this->testChatId, $cat->id);
        $this->assertNotNull($found);
        $this->assertSame($cat->id, $found->id);
    }

    public function testDeleteCategory(): void
    {
        $cat = $this->db->categories()->createCategory(
            chatId: $this->testChatId,
            name: 'Temporary Hobby',
            icon: '🎨',
        );

        $this->assertNotNull($cat->id);
        $catId = $cat->id;

        $deleted = $this->db->categories()->deleteCategory($this->testChatId, $catId);
        $this->assertTrue($deleted);

        $notFound = $this->db->categories()->findForChat($this->testChatId, $catId);
        $this->assertNull($notFound);

        $deleteInvalid = $this->db->categories()->deleteCategory($this->testChatId, 999999);
        $this->assertFalse($deleteInvalid);
    }
}
