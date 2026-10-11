<?php

declare(strict_types=1);

namespace Tueen\Telegram\Example\ToDoList\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Telegram\Example\ToDoList\Entity\Category;

/**
 * @extends EntityRepository<Category>
 */
class CategoryRepository extends EntityRepository
{
    /**
     * Standard predefined categories for new users.
     */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Work', 'icon' => '💼', 'color' => '#3b82f6'],
        ['name' => 'Personal', 'icon' => '👤', 'color' => '#10b981'],
        ['name' => 'Education', 'icon' => '🎓', 'color' => '#8b5cf6'],
        ['name' => 'Programming', 'icon' => '💻', 'color' => '#f59e0b'],
    ];

    /**
     * @return list<Category>
     */
    public function findByChat(int $chatId): array
    {
        $categories = $this->createQueryBuilder('c')
            ->where('c.chatId = :chatId')
            ->setParameter('chatId', $chatId)
            ->orderBy('c.isDefault', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($categories)) {
            return $this->seedDefaults($chatId);
        }

        return $categories;
    }

    /**
     * Seeds initial default categories for a chat if none exist.
     *
     * @return list<Category>
     */
    public function seedDefaults(int $chatId): array
    {
        $em = $this->getEntityManager();
        $seeded = [];

        foreach (self::DEFAULT_CATEGORIES as $def) {
            $cat = new Category(
                chatId: $chatId,
                name: $def['name'],
                icon: $def['icon'],
                color: $def['color'],
                isDefault: true,
            );
            $em->persist($cat);
            $seeded[] = $cat;
        }

        $em->flush();
        return $seeded;
    }

    public function findForChat(int $chatId, int $id): ?Category
    {
        return $this->findOneBy(['chatId' => $chatId, 'id' => $id]);
    }

    public function createCategory(int $chatId, string $name, string $icon = '📁', ?string $color = null): Category
    {
        $cleanName = trim($name);
        $existing = $this->findOneBy(['chatId' => $chatId, 'name' => $cleanName]);
        if ($existing !== null) {
            return $existing;
        }

        $em = $this->getEntityManager();
        $cat = new Category(
            chatId: $chatId,
            name: $cleanName,
            icon: $icon,
            color: $color,
            isDefault: false,
        );

        $em->persist($cat);
        $em->flush();

        return $cat;
    }

    public function deleteCategory(int $chatId, int $id): bool
    {
        $cat = $this->findForChat($chatId, $id);
        if ($cat === null) {
            return false;
        }

        $em = $this->getEntityManager();
        $em->remove($cat);
        $em->flush();

        return true;
    }
}
