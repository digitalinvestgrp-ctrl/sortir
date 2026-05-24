<?php
/**
 * Runner one-shot migrations Trouvetateam
 *
 * Usage prod :
 *   1. Upload via FTP `/ttt/migrations/run.php` + tous les `.sql`
 *   2. Curl `https://www.trouvetateam.fr/migrations/run.php?token=<MIGRATIONS_TOKEN>`
 *   3. Verifier output JSON
 *   4. Supprimer le fichier `run.php` apres usage (securite)
 *
 * Pattern Agendia + Jurenys (script one-shot, token de garde)
 */
declare(strict_types=1);

// Token de garde (a setter dans .env)
$expectedToken = $_ENV['MIGRATIONS_TOKEN'] ?? getenv('MIGRATIONS_TOKEN');
if (!$expectedToken) {
    // Fallback : on lit .env directement (avant Bootstrap)
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), 'MIGRATIONS_TOKEN=')) {
                $expectedToken = trim(substr(trim($line), 17), " \t\"'");
                break;
            }
        }
    }
}

$providedToken = $_GET['token'] ?? '';
if (!$expectedToken || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden', 'message' => 'Token invalide']);
    exit;
}

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();

header('Content-Type: application/json; charset=utf-8');

$pdo = \App\Core\Pdo::instance();

// Bootstrap : creer migrations_log si pas presente (auto-bootstrap)
$pdo->exec("
CREATE TABLE IF NOT EXISTS migrations_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL UNIQUE,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  duration_ms INT NULL,
  checksum VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$alreadyApplied = [];
foreach ($pdo->query('SELECT filename FROM migrations_log')->fetchAll(\PDO::FETCH_COLUMN) as $f) {
    $alreadyApplied[$f] = true;
}

$migrationsDir = __DIR__;
$files = glob($migrationsDir . '/*.sql');
sort($files);

$results = [];
$failed = false;

foreach ($files as $file) {
    $basename = basename($file);
    if (isset($alreadyApplied[$basename])) {
        $results[] = ['file' => $basename, 'status' => 'skipped', 'reason' => 'already applied'];
        continue;
    }

    $sql = file_get_contents($file);
    $checksum = hash('sha256', $sql);
    $start = microtime(true);

    try {
        // Split sur ; en fin de ligne (statements multiples)
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)),
            fn($s) => $s !== ''
        );
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        $duration = (int) ((microtime(true) - $start) * 1000);
        $log = $pdo->prepare('INSERT INTO migrations_log (filename, duration_ms, checksum) VALUES (?, ?, ?)');
        $log->execute([$basename, $duration, $checksum]);
        $results[] = ['file' => $basename, 'status' => 'applied', 'duration_ms' => $duration];
    } catch (\Throwable $e) {
        $results[] = ['file' => $basename, 'status' => 'failed', 'error' => $e->getMessage()];
        $failed = true;
        break;
    }
}

http_response_code($failed ? 500 : 200);
echo json_encode([
    'success' => !$failed,
    'time' => gmdate('Y-m-d H:i:s'),
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
