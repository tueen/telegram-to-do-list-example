<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Telegram\Example\ToDoList\Repository\CategoryRepository;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'todo_categories')]
#[ORM\Index(name: 'idx_todo_categories_chat', columns: ['chat_id'])]
#[ORM\UniqueConstraint(name: 'uk_todo_categories_chat_name', columns: ['chat_id', 'name'])]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'chat_id', type: Types::BIGINT)]
    public private(set) int $chatId;

    #[ORM\Column(type: Types::STRING, length: 100)]
    public string $name;

    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $icon = '📁';

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    public ?string $color = null;

    #[ORM\Column(name: 'is_default', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isDefault = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    public string $displayName {
        get => ($this->icon !== '' ? $this->icon . ' ' : '') . $this->name;
    }

    public function __construct(
        int $chatId,
        string $name,
        string $icon = '📁',
        ?string $color = null,
        bool $isDefault = false,
    ) {
        $this->chatId = $chatId;
        $this->name = trim($name);
        $this->icon = $icon;
        $this->color = $color;
        $this->isDefault = $isDefault;
        $this->createdAt = new DateTimeImmutable();
    }
}
