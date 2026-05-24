<?php
declare(strict_types=1);

/**
 * Healthz — endpoint de monitoring (pattern Agendia)
 * Verifie : DB up, version PHP, driver SMS, age min.
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';

\App\Core\Bootstrap::init();

$status = [
    'app' => 'trouvetateam',
    'status' => 'ok',
    'php_version' => PHP_VERSION,
    'time_utc' => gmdate('Y-m-d H:i:s'),
    'db' => false,
    'sms_driver' => $_ENV['SMS_DRIVER'] ?? 'log',
    'min_age' => (int) ($_ENV['MIN_AGE'] ?? 18),
];

try {
    $pdo = \App\Core\Pdo::instance();
    $row = $pdo->query('SELECT 1 AS ok')->fetch(\PDO::FETCH_ASSOC);
    $status['db'] = ($row['ok'] ?? 0) === 1;
} catch (\Throwable $e) {
    $status['status'] = 'degraded';
    $status['db_error'] = $e->getMessage();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
