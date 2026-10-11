<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tueen\Telegram\Example\ToDoList\Entity\Category;

class CategoryEntityTest extends TestCase
{
    public function testCategoryDisplayNameHook(): void
    {
        $cat = new Category(2002, 'Education', '🎓', '#8b5cf6', true);

        $this->assertSame(2002, $cat->chatId);
        $this->assertSame('Education', $cat->name);
        $this->assertSame('🎓', $cat->icon);
        $this->assertTrue($cat->isDefault);
        $this->assertSame('🎓 Education', $cat->displayName);
    }
}
