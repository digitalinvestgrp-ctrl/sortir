<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class BlockRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function createOrFind(int $blockerId, int $blockedId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO blocks (blocker_user_id, blocked_user_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $stmt->execute([$blockerId, $blockedId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $blockerId, int $blockedId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM blocks WHERE blocker_user_id = ? AND blocked_user_id = ?'
        );
        return $stmt->execute([$blockerId, $blockedId]);
    }

    public function isBlocked(int $blockerId, int $blockedId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM blocks WHERE blocker_user_id = ? AND blocked_user_id = ? LIMIT 1'
        );
        $stmt->execute([$blockerId, $blockedId]);
        return (bool) $stmt->fetchColumn();
    }
}
