<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;
use Tueen\Telegram\Example\ToDoList\Repository\TaskReminderRepository;

#[ORM\Entity(repositoryClass: TaskReminderRepository::class)]
#[ORM\Table(name: 'todo_task_reminders')]
#[ORM\Index(name: 'idx_todo_reminders_remind_sent', columns: ['remind_at', 'is_sent'])]
#[ORM\Index(name: 'idx_todo_reminders_chat', columns: ['chat_id'])]
class TaskReminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public Task $task;

    #[ORM\Column(name: 'chat_id', type: Types::BIGINT)]
    public private(set) int $chatId;

    #[ORM\Column(name: 'remind_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $remindAt;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: ReminderType::class)]
    public ReminderType $type;

    #[ORM\Column(name: 'is_sent', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isSent = false;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $sentAt = null;

    #[ORM\Column(name: 'snooze_count', type: Types::INTEGER, options: ['default' => 0])]
    public int $snoozeCount = 0;

    #[ORM\Column(name: 'snoozed_until', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $snoozedUntil = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    public function __construct(
        Task $task,
        DateTimeImmutable $remindAt,
        ReminderType $type = ReminderType::Scheduled,
    ) {
        $this->task = $task;
        $this->chatId = $task->chatId;
        $this->remindAt = $remindAt;
        $this->type = $type;
        $this->isSent = false;
        $this->snoozeCount = 0;
        $this->createdAt = new DateTimeImmutable();
    }

    public function markSent(): void
    {
        $this->isSent = true;
        $this->sentAt = new DateTimeImmutable();
    }

    public function snooze(DateTimeImmutable $until): void
    {
        $this->remindAt = $until;
        $this->snoozedUntil = $until;
        $this->isSent = false;
        $this->snoozeCount++;
    }
}
