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

    'csrf_secret' => $_ENV['CSRF_SECRET'] ?? '',

    'mail' => [
        // Pas de SMTP paye (regle DIG). Les liens magiques partent en best-effort via mail()
        // ET sont toujours journalises dans logs/outbox.jsonl (verifiable via /dev/mails?key=).
        'from' => $_ENV['MAIL_FROM'] ?? 'no-reply@trouvetateam.fr',
        // La page /dev/mails n'est accessible QUE si cette cle est fournie en query (?key=).
        // Vide => page desactivee (404). Protege contre toute prise de compte via l'outbox.
        'dev_key' => $_ENV['MAIL_DEV_KEY'] ?? '',
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
