<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Request — encapsule les inputs HTTP (query, body JSON, path params, user authentifie)
 */
class Request
{
    public array $params;
    public ?array $user;
    private ?array $body = null;

    public function __construct(array $params = [], ?array $user = null)
    {
        $this->params = $params;
        $this->user = $user;
    }

    /** Body JSON parse (lazy) */
    public function body(): array
    {
        if ($this->body === null) {
            $raw = file_get_contents('php://input') ?: '';
            $this->body = $raw === '' ? [] : (json_decode($raw, true) ?: []);
        }
        return $this->body;
    }

    public function input(string $key, $default = null)
    {
        $body = $this->body();
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $_GET[$key] ?? $default;
    }

    public function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return $this->user['id'] ?? null;
    }
}
