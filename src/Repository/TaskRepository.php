<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Tueen\Telegram\Example\ToDoList\Entity\Category;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Enum\TaskPriority;
use Tueen\Telegram\Example\ToDoList\Enum\TaskRecurrence;
use Tueen\Telegram\Example\ToDoList\Enum\TaskStatus;

/**
 * @extends EntityRepository<Task>
 */
class TaskRepository extends EntityRepository
{
    /**
     * Finds active (non-deleted) tasks for a chat with flexible filters and sorting.
     *
     * @return list<Task>
     */
    public function findActiveByChat(
        int $chatId,
        ?TaskStatus $status = null,
        ?TaskPriority $priority = null,
        ?int $categoryId = null,
        ?string $search = null,
        string $sort = 'newest',
        int $limit = 10,
        int $offset = 0,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->where('t.chatId = :chatId')
            ->andWhere('t.isDeleted = false')
            ->setParameter('chatId', $chatId);

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if ($priority !== null) {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', $priority);
        }

        if ($categoryId !== null) {
            if ($categoryId === 0) {
                // 0 means uncategorized
                $qb->andWhere('t.category IS NULL');
            } else {
                $qb->andWhere('t.category = :categoryId')
                    ->setParameter('categoryId', $categoryId);
            }
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('(LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search)')
                ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        match ($sort) {
            'oldest' => $qb->orderBy('t.createdAt', 'ASC'),
            'deadline' => $qb->addOrderBy('CASE WHEN t.deadline IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('t.deadline', 'ASC')
                ->addOrderBy('t.createdAt', 'DESC'),
            'priority' => $qb->addOrderBy("CASE t.priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4 END", 'ASC')
                ->addOrderBy('t.createdAt', 'DESC'),
            default => $qb->orderBy('t.createdAt', 'DESC'),
        };

        return $qb->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts active tasks matching the given filter.
     */
    public function countActiveByChat(
        int $chatId,
        ?TaskStatus $status = null,
        ?TaskPriority $priority = null,
        ?int $categoryId = null,
        ?string $search = null,
    ): int {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.chatId = :chatId')
            ->andWhere('t.isDeleted = false')
            ->setParameter('chatId', $chatId);

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if ($priority !== null) {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', $priority);
        }

        if ($categoryId !== null) {
            if ($categoryId === 0) {
                $qb->andWhere('t.category IS NULL');
            } else {
                $qb->andWhere('t.category = :categoryId')
                    ->setParameter('categoryId', $categoryId);
            }
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('(LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search)')
                ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Finds tasks currently in the trash (soft-deleted).
     *
     * @return list<Task>
     */
    public function findTrashByChat(int $chatId, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.chatId = :chatId')
            ->andWhere('t.isDeleted = true')
            ->setParameter('chatId', $chatId)
            ->orderBy('t.deletedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countTrashByChat(int $chatId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.chatId = :chatId')
            ->andWhere('t.isDeleted = true')
            ->setParameter('chatId', $chatId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function emptyTrash(int $chatId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.chatId = :chatId')
            ->andWhere('t.isDeleted = true')
            ->setParameter('chatId', $chatId)
            ->getQuery()
            ->execute();
    }

    public function findForChat(int $chatId, int $id): ?Task
    {
        return $this->findOneBy(['chatId' => $chatId, 'id' => $id]);
    }

    public function findByPublicId(int $chatId, string $publicId): ?Task
    {
        return $this->findOneBy(['chatId' => $chatId, 'publicId' => $publicId]);
    }

    /**
     * Compiles task dashboard statistics for a given user.
     *
     * @return array{total: int, pending: int, inProgress: int, completed: int, overdue: int, trash: int}
     */
    public function getStats(int $chatId): array
    {
        $now = new DateTimeImmutable();

        $rows = $this->createQueryBuilder('t')
            ->select('t.status', 't.isDeleted', 't.deadline')
            ->where('t.chatId = :chatId')
            ->setParameter('chatId', $chatId)
            ->getQuery()
            ->getArrayResult();

        $stats = [
            'total' => 0,
            'pending' => 0,
            'inProgress' => 0,
            'completed' => 0,
            'overdue' => 0,
            'trash' => 0,
        ];

        foreach ($rows as $row) {
            $isDeleted = (bool) $row['isDeleted'];
            if ($isDeleted) {
                $stats['trash']++;
                continue;
            }

            $stats['total']++;
            $statusVal = $row['status'] instanceof TaskStatus ? $row['status']->value : (string) $row['status'];

            match ($statusVal) {
                TaskStatus::Pending->value => $stats['pending']++,
                TaskStatus::InProgress->value => $stats['inProgress']++,
                TaskStatus::Completed->value => $stats['completed']++,
                default => null,
            };

            $deadline = $row['deadline'];
            if ($deadline !== null && $statusVal !== TaskStatus::Completed->value && $statusVal !== TaskStatus::Cancelled->value) {
                if ($deadline instanceof \DateTimeInterface && $deadline < $now) {
                    $stats['overdue']++;
                }
            }
        }

        return $stats;
    }

    public function createTask(
        int $chatId,
        string $title,
        ?string $description = null,
        TaskPriority $priority = TaskPriority::Medium,
        ?Category $category = null,
        ?DateTimeImmutable $deadline = null,
        ?DateTimeImmutable $reminderAt = null,
        TaskRecurrence $recurrence = TaskRecurrence::None,
    ): Task {
        $task = new Task(
            chatId: $chatId,
            title: $title,
            description: $description,
            priority: $priority,
            category: $category,
            deadline: $deadline,
            reminderAt: $reminderAt,
            recurrence: $recurrence,
        );

        $em = $this->getEntityManager();
        $em->persist($task);
        $em->flush();

        return $task;
    }

    public function save(Task $task): void
    {
        $em = $this->getEntityManager();
        $em->persist($task);
        $em->flush();
    }

    public function deletePermanent(Task $task): void
    {
        $em = $this->getEntityManager();
        $em->remove($task);
        $em->flush();
    }
}
