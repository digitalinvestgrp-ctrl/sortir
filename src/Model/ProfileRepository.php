<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class ProfileRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO profiles (user_id, display_name, neighborhood_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['display_name'],
            $data['neighborhood_id'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function incrementAttended(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE profiles SET attended_count = attended_count + 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}
