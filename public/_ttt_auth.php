<?php
declare(strict_types=1);
/**
 * _ttt_auth.php — Couche d'authentification WEB (cookie/session) pour les pages
 * rendues cote serveur de TrouveTaTeam.
 *
 * PROD 2026-10 — dig-holding : Mehdi Benali (archi) / Clara Dupont (back) / Lukas Brennan (front).
 *
 * Principe : on NE reinvente PAS l'auth. On REUTILISE la table `api_tokens` existante
 * (meme schema SHA256 que l'API Bearer) pour stocker :
 *   - les SESSIONS web     : name = 'session', cookie `ttt_sid` = token en clair, TTL 30 j.
 *   - les LIENS MAGIQUES    : name = 'magic',  TTL 30 min, usage unique (DELETE a la conso),
 *                             le chemin de redirection "next" est stocke dans `abilities`.
 *
 * Aucune modification de l'API : ce fichier parle directement a la base via Pdo::instance(),
 * exactement comme home.php / sorties.php / membres.php.
 *
 * Robustesse : toute lecture "utilisateur courant" est encapsulee dans un try/catch — une
 * panne base ne doit JAMAIS casser l'affichage des pages publiques.
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';
\App\Core\Bootstrap::init();

use App\Core\Bootstrap;
use App\Core\Pdo;

if (!defined('TTT_SESSION_COOKIE')) {
    define('TTT_SESSION_COOKIE', 'ttt_sid');
    define('TTT_CSRF_COOKIE', 'ttt_csrf');
    define('TTT_FLASH_COOKIE', 'ttt_flash');
    define('TTT_SESSION_TTL_DAYS', 30);
    define('TTT_MAGIC_TTL_MIN', 30);
}

/** PDO partage (meme singleton que le reste du site). */
function ttt_pdo(): \PDO
{
    return Pdo::instance();
}

/** Base URL publique sans slash final (ex: https://www.trouvetateam.fr). */
function ttt_base_url(): string
{
    $url = (string) Bootstrap::config('url', 'https://trouvetateam.fr');
    return rtrim($url, '/');
}

/**
 * Domaine enregistrable courant pour l'attribut Domain des cookies
 * (ex: '.trouvetateam.fr' ou '.trouvetateam.com' selon l'hote).
 * Retourne '' pour localhost/IP (cookie host-only, cas du dev).
 * Ainsi www et apex du MEME domaine partagent la session.
 */
function ttt_cookie_domain(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host); // retire le port
    if (preg_match('/(^|\.)(trouvetateam\.(?:fr|com))$/', $host, $m)) {
        return '.' . $m[2];
    }
    return '';
}

/**
 * Base canonique (https://www.<domaine courant>) pour les URL ABSOLUES
 * (lien de partage, lien magique). Reste sur le domaine ou se trouve l'utilisateur.
 */
function ttt_canonical_base(): string
{
    $dom = ttt_cookie_domain(); // .trouvetateam.fr | .trouvetateam.com | ''
    if ($dom !== '') {
        return 'https://www' . $dom;
    }
    return ttt_base_url();
}

/** Requete en HTTPS ? (pour le flag Secure des cookies). */
function ttt_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return str_starts_with(ttt_base_url(), 'https://');
}

/** Pose un cookie avec les bons attributs de securite (SameSite=Lax). */
function ttt_setcookie(string $name, string $value, int $maxAge): void
{
    $opts = [
        'expires' => $maxAge > 0 ? time() + $maxAge : ($value === '' ? time() - 3600 : 0),
        'path' => '/',
        'secure' => ttt_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    // Domain = domaine enregistrable courant -> www et apex partagent la session.
    $dom = ttt_cookie_domain();
    if ($dom !== '') {
        $opts['domain'] = $dom;
    }
    setcookie($name, $value, $opts);
    // Reflette immediatement pour la requete courante.
    if ($value === '') {
        unset($_COOKIE[$name]);
    } else {
        $_COOKIE[$name] = $value;
    }
}

/* ============================================================
 *  SESSIONS WEB (cookie ttt_sid <-> api_tokens name='session')
 * ============================================================ */

/** Cree une session pour $userId, pose le cookie, retourne le token en clair. */
function ttt_login_user(int $userId): string
{
    $plain = bin2hex(random_bytes(32));
    $hash = hash('sha256', $plain);
    $expires = (new \DateTimeImmutable('+' . TTT_SESSION_TTL_DAYS . ' days'))->format('Y-m-d H:i:s');
    $stmt = ttt_pdo()->prepare(
        'INSERT INTO api_tokens (user_id, name, token_hash, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, 'session', $hash, $expires]);
    ttt_setcookie(TTT_SESSION_COOKIE, $plain, TTT_SESSION_TTL_DAYS * 86400);
    return $plain;
}

/** Detruit la session courante (revoque le token + supprime le cookie). */
function ttt_logout(): void
{
    $plain = $_COOKIE[TTT_SESSION_COOKIE] ?? '';
    if ($plain !== '') {
        try {
            $hash = hash('sha256', $plain);
            $stmt = ttt_pdo()->prepare("DELETE FROM api_tokens WHERE token_hash = ? AND name = 'session'");
            $stmt->execute([$hash]);
        } catch (\Throwable $e) {
            error_log('[ttt_auth] logout: ' . $e->getMessage());
        }
    }
    ttt_setcookie(TTT_SESSION_COOKIE, '', 0);
}

/**
 * Retourne la ligne utilisateur courante (ou null). Mise en cache statique.
 * Resiliente : toute erreur base renvoie null sans casser la page.
 */
function ttt_current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached !== false) {
        return $user;
    }
    $cached = true;
    $plain = $_COOKIE[TTT_SESSION_COOKIE] ?? '';
    if ($plain === '') {
        return $user = null;
    }
    try {
        $hash = hash('sha256', $plain);
        $pdo = ttt_pdo();
        $stmt = $pdo->prepare(
            "SELECT user_id, expires_at FROM api_tokens WHERE token_hash = ? AND name = 'session' LIMIT 1"
        );
        $stmt->execute([$hash]);
        $tok = $stmt->fetch();
        if (!$tok) {
            return $user = null;
        }
        if (!empty($tok['expires_at']) && strtotime((string) $tok['expires_at']) < time()) {
            return $user = null;
        }
        $us = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $us->execute([(int) $tok['user_id']]);
        $row = $us->fetch();
        if (!$row || (int) ($row['is_blocked'] ?? 0) === 1) {
            return $user = null;
        }
        // best-effort last_used_at
        try {
            $upd = $pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?");
            $upd->execute([$hash]);
        } catch (\Throwable $e) {
            // ignore
        }
        return $user = $row;
    } catch (\Throwable $e) {
        error_log('[ttt_auth] current_user: ' . $e->getMessage());
        return $user = null;
    }
}

function ttt_is_logged_in(): bool
{
    return ttt_current_user() !== null;
}

/** Exige une session ; sinon redirige vers /connexion?next=... */
function ttt_require_login(string $next = ''): array
{
    $u = ttt_current_user();
    if ($u === null) {
        $q = $next !== '' ? ('?next=' . rawurlencode($next)) : '';
        ttt_redirect('/connexion' . $q);
    }
    return $u;
}

/* ============================================================
 *  LIENS MAGIQUES (api_tokens name='magic', usage unique)
 * ============================================================ */

/** Cree un lien magique pour $userId, retourne [plain, url]. */
function ttt_issue_magic(int $userId, string $next = '/compte'): array
{
    $plain = bin2hex(random_bytes(32));
    $hash = hash('sha256', $plain);
    $expires = (new \DateTimeImmutable('+' . TTT_MAGIC_TTL_MIN . ' minutes'))->format('Y-m-d H:i:s');
    $next = ttt_safe_next($next);
    $stmt = ttt_pdo()->prepare(
        'INSERT INTO api_tokens (user_id, name, token_hash, abilities, expires_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, 'magic', $hash, $next, $expires]);
    $url = ttt_canonical_base() . '/auth/magic?t=' . $plain;
    return [$plain, $url];
}

/**
 * Verifie un token magique SANS le consommer (GET resistant au prefetch des scanners
 * d'email / SafeLinks). Retourne [user_id, next] si valide et non expire, sinon null.
 */
function ttt_peek_magic(string $plain): ?array
{
    if ($plain === '' || !ctype_xdigit($plain)) {
        return null;
    }
    try {
        $hash = hash('sha256', $plain);
        $stmt = ttt_pdo()->prepare(
            "SELECT user_id, abilities, expires_at FROM api_tokens WHERE token_hash = ? AND name = 'magic' LIMIT 1"
        );
        $stmt->execute([$hash]);
        $tok = $stmt->fetch();
        if (!$tok) {
            return null;
        }
        if (!empty($tok['expires_at']) && strtotime((string) $tok['expires_at']) < time()) {
            return null;
        }
        return [(int) $tok['user_id'], ttt_safe_next((string) ($tok['abilities'] ?? '/compte'))];
    } catch (\Throwable $e) {
        error_log('[ttt_auth] peek_magic: ' . $e->getMessage());
        return null;
    }
}

/**
 * Consomme un token magique (usage unique). Retourne [user_id, next] ou null.
 * Supprime le token quelle que soit l'issue (anti-rejeu).
 */
function ttt_consume_magic(string $plain): ?array
{
    if ($plain === '' || !ctype_xdigit($plain)) {
        return null;
    }
    try {
        $pdo = ttt_pdo();
        $hash = hash('sha256', $plain);
        $stmt = $pdo->prepare(
            "SELECT id, user_id, abilities, expires_at FROM api_tokens WHERE token_hash = ? AND name = 'magic' LIMIT 1"
        );
        $stmt->execute([$hash]);
        $tok = $stmt->fetch();
        if (!$tok) {
            return null;
        }
        // usage unique : on supprime tout de suite
        $del = $pdo->prepare('DELETE FROM api_tokens WHERE id = ?');
        $del->execute([(int) $tok['id']]);
        if (!empty($tok['expires_at']) && strtotime((string) $tok['expires_at']) < time()) {
            return null;
        }
        return [(int) $tok['user_id'], ttt_safe_next((string) ($tok['abilities'] ?? '/compte'))];
    } catch (\Throwable $e) {
        error_log('[ttt_auth] consume_magic: ' . $e->getMessage());
        return null;
    }
}

/* ============================================================
 *  CSRF (double-submit cookie)
 * ============================================================ */

function ttt_csrf_token(): string
{
    $tok = $_COOKIE[TTT_CSRF_COOKIE] ?? '';
    if ($tok === '' || !ctype_xdigit($tok) || strlen($tok) < 32) {
        $tok = bin2hex(random_bytes(32));
        ttt_setcookie(TTT_CSRF_COOKIE, $tok, 86400 * 7);
    }
    return $tok;
}

/** Champ hidden CSRF a inserer dans tout formulaire POST. */
function ttt_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(ttt_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Verifie le token CSRF du POST. Termine la requete en 403 si invalide. */
function ttt_check_csrf(): void
{
    $cookie = $_COOKIE[TTT_CSRF_COOKIE] ?? '';
    $posted = $_POST['_csrf'] ?? '';
    if ($cookie === '' || $posted === '' || !hash_equals($cookie, (string) $posted)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><h1>403 — Session expiree</h1>'
            . '<p>Ta session a expire. <a href="/connexion">Reconnecte-toi</a> puis reessaie.</p>';
        exit;
    }
}

/* ============================================================
 *  FLASH messages (cookie court)
 * ============================================================ */

function ttt_flash_set(string $type, string $message): void
{
    $payload = json_encode(['t' => $type, 'm' => $message], JSON_UNESCAPED_UNICODE);
    ttt_setcookie(TTT_FLASH_COOKIE, rawurlencode((string) $payload), 120);
}

/** Lit et efface le flash. Retourne ['t'=>..., 'm'=>...] ou null. */
function ttt_flash_pull(): ?array
{
    $raw = $_COOKIE[TTT_FLASH_COOKIE] ?? '';
    if ($raw === '') {
        return null;
    }
    ttt_setcookie(TTT_FLASH_COOKIE, '', 0);
    $data = json_decode(rawurldecode($raw), true);
    if (!is_array($data) || empty($data['m'])) {
        return null;
    }
    return ['t' => (string) ($data['t'] ?? 'info'), 'm' => (string) $data['m']];
}

/* ============================================================
 *  Redirections / next sanitization
 * ============================================================ */

/** Autorise uniquement des chemins internes connus (anti open-redirect). */
function ttt_safe_next(?string $next): string
{
    $next = trim((string) $next);
    if ($next === '') {
        return '/compte';
    }
    // chemins internes seulement
    if ($next[0] !== '/' || str_starts_with($next, '//')) {
        return '/compte';
    }
    if (preg_match('#^/(compte|creer|sorties|membres|connexion)$#', $next)) {
        return $next;
    }
    if (preg_match('#^/sortie/\d+(/modifier)?$#', $next)) {
        return $next;
    }
    if ($next === '/') {
        return '/';
    }
    return '/compte';
}

function ttt_redirect(string $to, int $code = 303): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Location: ' . $to);
    }
    echo '<!doctype html><meta charset="utf-8"><title>Redirection</title>'
        . '<p>Redirection vers <a href="' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '</a></p>';
    exit;
}

/* ============================================================
 *  MAIL (lien magique) — best effort + outbox verifiable
 * ============================================================ */

/**
 * "Envoie" un lien magique : tente mail() (best effort) et journalise TOUJOURS
 * dans l'outbox (logs/outbox.jsonl) pour verification via /dev/mails?key=...
 */
function ttt_send_magic_mail(array $user, string $url, string $kind = 'login'): void
{
    $to = (string) ($user['email'] ?? '');
    $name = (string) ($user['name'] ?? '');
    $from = (string) Bootstrap::config('mail.from', 'no-reply@trouvetateam.fr');
    $subject = $kind === 'welcome'
        ? 'Bienvenue sur TrouveTaTeam — confirme ton email'
        : 'Ton lien de connexion TrouveTaTeam';
    $body = "Bonjour " . ($name !== '' ? $name : '') . ",\n\n"
        . "Voici ton lien de connexion a TrouveTaTeam (valable 30 minutes, usage unique) :\n\n"
        . $url . "\n\n"
        . "Si tu n'es pas a l'origine de cette demande, ignore ce message.\n\n"
        . "— L'equipe TrouveTaTeam";

    // Outbox (toujours) : preuve + fallback sans SMTP.
    ttt_outbox_append([
        'ts' => date('c'),
        'to' => $to,
        'kind' => $kind,
        'subject' => $subject,
        'url' => $url,
    ]);

    // Best effort via mail() si une adresse email est presente.
    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $headers = 'From: TrouveTaTeam <' . $from . ">\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n"
            . "MIME-Version: 1.0\r\n";
        try {
            @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        } catch (\Throwable $e) {
            error_log('[ttt_auth] mail(): ' . $e->getMessage());
        }
    }
}

function ttt_outbox_path(): string
{
    return __DIR__ . '/../logs/outbox.jsonl';
}

function ttt_outbox_append(array $entry): void
{
    try {
        $path = ttt_outbox_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        error_log('[ttt_auth] outbox: ' . $e->getMessage());
    }
}

/** Les N derniers mails de l'outbox (plus recents en premier). */
function ttt_outbox_recent(int $limit = 30): array
{
    $path = ttt_outbox_path();
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($lines, -$limit);
    $out = [];
    foreach (array_reverse($lines) as $l) {
        $d = json_decode($l, true);
        if (is_array($d)) {
            $out[] = $d;
        }
    }
    return $out;
}

/** La cle qui protege /dev/mails (vide => page desactivee). */
function ttt_mail_dev_key(): string
{
    return (string) Bootstrap::config('mail.dev_key', '');
}
