<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class ReportRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function create(int $reporterUserId, string $type, int $targetId, string $reason, ?string $details = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO reports (reporter_user_id, reportable_type, reportable_id, reason, details, status)
             VALUES (?, ?, ?, ?, ?, 'open')"
        );
        $stmt->execute([$reporterUserId, $type, $targetId, $reason, $details]);
        return (int) $this->pdo->lastInsertId();
    }

    public function targetExists(string $type, int $id): bool
    {
        $table = $type === 'user' ? 'users' : ($type === 'event' ? 'events' : null);
        if (!$table) {
            return false;
        }
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}
