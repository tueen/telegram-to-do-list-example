<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskRecurrence;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;
use Tueen\Telegram\Example\ToDoList\Repository\TaskRepository;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'todo_tasks')]
#[ORM\Index(name: 'idx_todo_tasks_chat_status', columns: ['chat_id', 'status', 'is_deleted'])]
#[ORM\Index(name: 'idx_todo_tasks_chat_priority', columns: ['chat_id', 'priority'])]
#[ORM\Index(name: 'idx_todo_tasks_deadline', columns: ['deadline'])]
#[ORM\Index(name: 'idx_todo_tasks_reminder_at', columns: ['reminder_at'])]
#[ORM\UniqueConstraint(name: 'uk_todo_tasks_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'public_id', type: Types::STRING, length: 26)]
    public private(set) string $publicId;

    #[ORM\Column(name: 'chat_id', type: Types::BIGINT)]
    public private(set) int $chatId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TaskPriority::class)]
    public TaskPriority $priority = TaskPriority::Medium;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TaskStatus::class)]
    public TaskStatus $status = TaskStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    public ?Category $category = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $deadline = null;

    #[ORM\Column(name: 'reminder_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $reminderAt = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TaskRecurrence::class)]
    public TaskRecurrence $recurrence = TaskRecurrence::None;

    #[ORM\Column(name: 'is_deleted', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isDeleted = false;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    // PHP 8.4 Virtual Property Hooks
    public bool $isOverdue {
        get => !$this->isDeleted
            && !$this->status->isFinished()
            && $this->deadline !== null
            && $this->deadline < new DateTimeImmutable();
    }

    public string $shortTitle {
        get => mb_strlen($this->title) > 28 ? mb_substr($this->title, 0, 25) . '...' : $this->title;
    }

    public string $statusBadge {
        get => $this->status->badge();
    }

    public string $priorityBadge {
        get => $this->priority->badge();
    }

    public string $recurrenceBadge {
        get => $this->recurrence->badge();
    }

    public function __construct(
        int $chatId,
        string $title,
        ?string $description = null,
        TaskPriority $priority = TaskPriority::Medium,
        ?Category $category = null,
        ?DateTimeImmutable $deadline = null,
        ?DateTimeImmutable $reminderAt = null,
        TaskRecurrence $recurrence = TaskRecurrence::None,
        ?string $publicId = null,
    ) {
        $this->chatId = $chatId;
        $this->title = trim($title);
        $this->description = $description !== null ? trim($description) : null;
        $this->priority = $priority;
        $this->category = $category;
        $this->deadline = $deadline;
        $this->reminderAt = $reminderAt;
        $this->recurrence = $recurrence;
        $this->publicId = $publicId ?? Ulid::generate();
        $this->status = TaskStatus::Pending;
        $this->isDeleted = false;

        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markCompleted(): void
    {
        $this->status = TaskStatus::Completed;
        $this->completedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markPending(): void
    {
        $this->status = TaskStatus::Pending;
        $this->completedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markInProgress(): void
    {
        $this->status = TaskStatus::InProgress;
        $this->completedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markCancelled(): void
    {
        $this->status = TaskStatus::Cancelled;
        $this->completedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function softDelete(): void
    {
        $this->isDeleted = true;
        $this->deletedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->isDeleted = false;
        $this->deletedAt = null;
        $this->updatedAt = new DateTimeImmutable();
    }
}
