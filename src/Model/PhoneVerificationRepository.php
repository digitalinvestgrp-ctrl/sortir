<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class PhoneVerificationRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function create(int $userId, string $phone, string $codeHash, int $ttlMinutes = 10): int
    {
        $expires = (new \DateTimeImmutable("+{$ttlMinutes} minutes"))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO phone_verifications (user_id, phone, code_hash, attempts, expires_at) VALUES (?, ?, ?, 0, ?)'
        );
        $stmt->execute([$userId, $phone, $codeHash, $expires]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findLatestPending(int $userId, string $phone): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM phone_verifications
             WHERE user_id = ? AND phone = ? AND consumed_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $phone]);
        return $stmt->fetch() ?: null;
    }

    public function incrementAttempts(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE phone_verifications SET attempts = attempts + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function markConsumed(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE phone_verifications SET consumed_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function isExpired(array $row): bool
    {
        return strtotime($row['expires_at']) < time();
    }
}
