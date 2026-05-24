<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Logger — write to logs/{channel}.log
 */
class Logger
{
    public static function log(string $channel, string $level, string $message, array $context = []): void
    {
        $dir = __DIR__ . '/../../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            gmdate('Y-m-d H:i:s'),
            strtoupper($channel),
            strtoupper($level),
            $message,
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE)
        );
        @file_put_contents("{$dir}/{$channel}.log", $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::log($channel, 'info', $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::log($channel, 'error', $message, $context);
    }
}
