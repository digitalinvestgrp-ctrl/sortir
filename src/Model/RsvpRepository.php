<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class RsvpRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function createOrUpdate(int $eventId, int $userId, string $status = 'going'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()"
        );
        $stmt->execute([$eventId, $userId, $status]);
        return (int) ($this->pdo->lastInsertId() ?: $eventId);
    }

    public function listByEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rsvps WHERE event_id = ?');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }
}
