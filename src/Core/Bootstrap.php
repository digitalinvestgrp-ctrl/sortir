<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bootstrap Trouvetateam — init application (config, .env, autoload, PDO, error handler)
 * Pattern Agendia
 */
class Bootstrap
{
    private static bool $initialized = false;
    private static array $config = [];

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Load .env si present (non commite ; en prod le .env est sur le serveur)
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            self::loadEnv($envFile);
        }

        // Autoload PSR-4 simple (au cas ou Composer pas dispo en prod OVH mutu)
        spl_autoload_register([self::class, 'autoload']);

        // Load config
        self::$config = require __DIR__ . '/../../config/app.php';

        // Error handler global
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);

        // Default timezone Paris
        date_default_timezone_set('Europe/Paris');

        // Headers JSON par defaut sur tout endpoint api
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
        }

        self::$initialized = true;
    }

    public static function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return self::$config;
        }
        $parts = explode('.', $key);
        $val = self::$config;
        foreach ($parts as $p) {
            if (!is_array($val) || !array_key_exists($p, $val)) {
                return $default;
            }
            $val = $val[$p];
        }
        return $val;
    }

    private static function loadEnv(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    public static function autoload(string $class): void
    {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/../' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        error_log("[PHP-ERROR {$errno}] {$errstr} @ {$errfile}:{$errline}");
        return false;
    }

    public static function exceptionHandler(\Throwable $e): void
    {
        error_log('[UNCAUGHT] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'error' => 'Server error',
            'message' => self::config('debug') ? $e->getMessage() : 'Internal error',
        ]);
    }
}
