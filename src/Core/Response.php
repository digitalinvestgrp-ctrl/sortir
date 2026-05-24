<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Helper Response — sortie JSON normalisee
 */
class Response
{
    public static function json($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge(['error' => $message], $extra), $status);
    }

    public static function validationError(array $errors): void
    {
        self::json(['error' => 'Validation failed', 'errors' => $errors], 422);
    }
}
