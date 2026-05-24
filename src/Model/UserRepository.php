<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Pdo;

class UserRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, birthdate) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['password'],
            $data['birthdate'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function markPhoneVerified(int $userId, string $phone): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET phone = ?, phone_verified_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$phone, $userId]);
    }

    public function isAdult(array $userRow, int $minAge = 18): bool
    {
        if (empty($userRow['birthdate'])) {
            return false;
        }
        $age = (new \DateTimeImmutable($userRow['birthdate']))
            ->diff(new \DateTimeImmutable('now'))->y;
        return $age >= $minAge;
    }

    public function hasVerifiedPhone(array $userRow): bool
    {
        return !empty($userRow['phone_verified_at']);
    }

    public function payload(array $userRow, int $minAge = 18): array
    {
        return [
            'id' => (int) $userRow['id'],
            'name' => $userRow['name'],
            'email' => $userRow['email'],
            'age' => empty($userRow['birthdate']) ? null : (new \DateTimeImmutable($userRow['birthdate']))->diff(new \DateTimeImmutable('now'))->y,
            'is_adult' => $this->isAdult($userRow, $minAge),
            'phone_verified' => $this->hasVerifiedPhone($userRow),
        ];
    }
}
