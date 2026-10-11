<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Tueen\Telegram\Example\ToDoList\Entity\Task;
use Tueen\Telegram\Example\ToDoList\Entity\TaskReminder;
use Tueen\Telegram\Example\ToDoList\Enum\ReminderType;

/**
 * @extends EntityRepository<TaskReminder>
 */
class TaskReminderRepository extends EntityRepository
{
    /**
     * Finds active reminders due to be sent now or earlier.
     *
     * @return list<TaskReminder>
     */
    public function findDueReminders(DateTimeImmutable $now, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.task', 't')
            ->where('r.isSent = false')
            ->andWhere('r.remindAt <= :now')
            ->andWhere('t.isDeleted = false')
            ->andWhere("t.status NOT IN ('completed', 'cancelled')")
            ->setParameter('now', $now)
            ->orderBy('r.remindAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<TaskReminder>
     */
    public function findUpcomingForChat(int $chatId, int $limit = 10): array
    {
        $now = new DateTimeImmutable();

        return $this->createQueryBuilder('r')
            ->join('r.task', 't')
            ->where('r.chatId = :chatId')
            ->andWhere('r.isSent = false')
            ->andWhere('r.remindAt > :now')
            ->andWhere('t.isDeleted = false')
            ->setParameter('chatId', $chatId)
            ->setParameter('now', $now)
            ->orderBy('r.remindAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function createReminder(Task $task, DateTimeImmutable $remindAt, ReminderType $type = ReminderType::Scheduled): TaskReminder
    {
        $em = $this->getEntityManager();
        // If entity is detached or from a cleared EntityManager, re-merge or persist
        if (!$em->contains($task)) {
            $task = $em->find(Task::class, $task->id) ?? $task;
        }

        $reminder = new TaskReminder($task, $remindAt, $type);
        $em->persist($reminder);
        $em->flush();

        return $reminder;
    }

    public function save(TaskReminder $reminder): void
    {
        $em = $this->getEntityManager();
        $em->persist($reminder);
        $em->flush();
    }

    public function deleteForTask(int $taskId): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->where('r.task = :taskId')
            ->setParameter('taskId', $taskId)
            ->getQuery()
            ->execute();

        $this->getEntityManager()->clear();
    }
}
