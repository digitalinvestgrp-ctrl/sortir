<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class InterestGroupRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM interest_groups WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function listByCity(int $cityId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM interest_groups WHERE city_id = ? ORDER BY name');
        $stmt->execute([$cityId]);
        return $stmt->fetchAll();
    }

    public function addMember(int $groupId, int $userId, string $role = 'member'): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO interest_group_user (interest_group_id, user_id, role) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)"
        );
        $stmt->execute([$groupId, $userId, $role]);
    }
}
