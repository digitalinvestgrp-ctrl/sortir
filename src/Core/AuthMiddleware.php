<?php
declare(strict_types=1);

namespace App\Core;

use App\Model\UserRepository;
use App\Service\TokenService;

/**
 * AuthMiddleware — verifie le header Authorization: Bearer <token>
 * Token = hex 64 chars. Stocke en BDD comme SHA256 hash.
 */
class AuthMiddleware
{
    /**
     * @return array|null user row si auth OK, null sinon
     */
    public static function authenticate(): ?array
    {
        $token = self::extractToken();
        if (!$token) {
            return null;
        }

        $service = new TokenService();
        $userId = $service->resolveUserId($token);
        if (!$userId) {
            return null;
        }

        $repo = new UserRepository();
        $user = $repo->find($userId);
        if (!$user || (int) $user['is_blocked'] === 1) {
            return null;
        }

        $user['_token'] = $token;
        return $user;
    }

    private static function extractToken(): ?string
    {
        // Header Authorization
        $auth = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        // OVH mutu strip parfois le header Authorization -> fallback X-Auth-Token
        if (!$auth && isset($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            return trim($_SERVER['HTTP_X_AUTH_TOKEN']);
        }

        if (!$auth) {
            return null;
        }
        if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return null;
        }
        return trim($m[1]);
    }
}
