<?php
/**
 * Configuration centrale Trouvetateam (pattern Agendia)
 *
 * Les valeurs proviennent du .env (NEVER commit). Defaults safe ici.
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'trouvetateam',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'url' => $_ENV['APP_URL'] ?? 'https://www.trouvetateam.fr',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'key' => $_ENV['APP_KEY'] ?? '',
    'min_age' => (int) ($_ENV['MIN_AGE'] ?? 18),
    'default_radius_m' => (int) ($_ENV['DEFAULT_RADIUS_M'] ?? 3000),
    'max_radius_m' => (int) ($_ENV['MAX_RADIUS_M'] ?? 10000),

    'db' => [
        'host' => $_ENV['DB_HOST'] ?? 'cs1023012-002.eu.clouddb.ovh.net',
        'port' => (int) ($_ENV['DB_PORT'] ?? 35114),
        'user' => $_ENV['DB_USER'] ?? 'safeprotek',
        'pass' => $_ENV['DB_PASS'] ?? '',
        'name' => $_ENV['DB_NAME'] ?? 'trouvetateam',
        'charset' => 'utf8mb4',
    ],

    'sms' => [
        'driver' => $_ENV['SMS_DRIVER'] ?? 'log',
        'otp' => [
            'length' => 6,
            'ttl_minutes' => 10,
            'max_attempts' => 5,
        ],
        'drivers' => [
            'log' => [
                'expose_code_in_dev' => filter_var($_ENV['SMS_EXPOSE_CODE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            ],
        ],
    ],

    'logging' => [
        'path' => __DIR__ . '/../logs',
        'channels' => ['app', 'sms', 'errors'],
    ],
];
