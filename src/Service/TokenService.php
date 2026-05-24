<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Pdo;

/**
 * TokenService — remplace Sanctum
 *
 * Generation : random_bytes(32) -> hex 64 chars
 * Stockage BDD : SHA256(token) en colonne token_hash
 * Verif : SHA256(provided_token) compare en SELECT
 */
class TokenService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Pdo::instance();
    }

    /**
     * Cree un token pour un user et retourne le token en clair (a renvoyer une seule fois).
     */
    public function createForUser(int $userId, string $name = 'api', ?int $ttlDays = null): string
    {
        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expires = $ttlDays ? (new \DateTimeImmutable("+{$ttlDays} days"))->format('Y-m-d H:i:s') : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_tokens (user_id, name, token_hash, expires_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $name, $hash, $expires]);

        return $plain;
    }

    /**
     * Retourne user_id si token valide (et non expire), null sinon.
     * Met a jour last_used_at au passage.
     */
    public function resolveUserId(string $plainToken): ?int
    {
        $hash = hash('sha256', $plainToken);
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, expires_at FROM api_tokens WHERE token_hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return null;
        }
        // Update last_used_at (best effort)
        $upd = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?');
        $upd->execute([(int) $row['id']]);

        return (int) $row['user_id'];
    }

    public function revoke(string $plainToken): bool
    {
        $hash = hash('sha256', $plainToken);
        $stmt = $this->pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?');
        return $stmt->execute([$hash]);
    }

    public function revokeAllForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
}
