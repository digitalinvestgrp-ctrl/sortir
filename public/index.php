<?php
declare(strict_types=1);

/**
 * Trouvetateam — Front controller PHP natif
 *
 * Dispatch des routes API vers les controllers PSR-4 (src/Controller/*).
 * Pattern Agendia : routing par tableau, middleware Auth Bearer pour routes protegees.
 */

require_once __DIR__ . '/../src/Core/Bootstrap.php';

use App\Core\Router;
use App\Core\Response;

// Init application (PDO, config, autoload, error handler)
\App\Core\Bootstrap::init();

// Landing publique : / et /accueil sont rendus cote serveur par home.php (design Node,
// liste des vraies sorties MySQL). Fallback : landing statique index.html si home.php absent.
// /index.html reste servi statiquement (landing Node statique) par Apache / ce fallback.
// Garantit que le visiteur ne tombe jamais sur un JSON 404 a la racine.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && in_array($requestPath, ['/', '/accueil'], true)) {
    $home = __DIR__ . '/home.php';
    if (is_file($home)) {
        require $home;
        return;
    }
    $landing = __DIR__ . '/index.html';
    if (is_file($landing)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        readfile($landing);
        return;
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $requestPath === '/index.html') {
    $landing = __DIR__ . '/index.html';
    if (is_file($landing)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        readfile($landing);
        return;
    }
}

// Pages publiques /sorties et /membres (extension-less, rendues par sorties.php / membres.php)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && in_array($requestPath, ['/sorties', '/membres'], true)) {
    $page = __DIR__ . $requestPath . '.php';
    if (is_file($page)) {
        require $page;
        return;
    }
}

// --- Pages applicatives rendues cote serveur (auth, sorties interactives, compte) ---
// Ces pages gerent elles-memes GET et POST (formulaires + CSRF). Mapping chemin -> fichier.
$ttt_pages = [
    '/connexion' => 'connexion.php',
    '/auth/magic' => 'auth_magic.php',
    '/deconnexion' => 'deconnexion.php',
    '/compte' => 'compte.php',
    '/creer' => 'creer.php',
    '/dev/mails' => 'dev_mails.php',
];
if (isset($ttt_pages[$requestPath])) {
    $page = __DIR__ . '/' . $ttt_pages[$requestPath];
    if (is_file($page)) {
        require $page;
        return;
    }
}
// /sortie/{id}/modifier (avant /sortie/{id})
if (preg_match('#^/sortie/(\d+)/modifier$#', $requestPath, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/modifier.php';
    return;
}
// /sortie/{id} (fichier nomme sortie_detail.php pour eviter la collision MultiViews
// entre l'URL /sortie/{id} et un fichier public/sortie.php)
if (preg_match('#^/sortie/(\d+)$#', $requestPath, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/sortie_detail.php';
    return;
}

$router = new Router();

// --- Health (libre) ---
$router->get('/api/health', ['App\\Controller\\HealthController', 'index']);

// --- Auth (libre) ---
$router->post('/api/auth/register', ['App\\Controller\\AuthController', 'register']);
$router->post('/api/auth/login', ['App\\Controller\\AuthController', 'login']);

// --- Discovery libre (consultation sans compte, parcours produit Adrien) ---
$router->get('/api/discovery/nearby', ['App\\Controller\\DiscoveryController', 'nearby']);
$router->get('/api/discovery/neighborhood/{slug}', ['App\\Controller\\DiscoveryController', 'byNeighborhood']);
$router->get('/api/cities/{slug}/neighborhoods', ['App\\Controller\\DiscoveryController', 'neighborhoods']);

// --- Routes authentifiees (Bearer token) ---
$router->get('/api/auth/me', ['App\\Controller\\AuthController', 'me'], true);
$router->post('/api/auth/logout', ['App\\Controller\\AuthController', 'logout'], true);
$router->post('/api/phone/request', ['App\\Controller\\PhoneVerificationController', 'request'], true);
$router->post('/api/phone/confirm', ['App\\Controller\\PhoneVerificationController', 'confirm'], true);
$router->post('/api/trust/report', ['App\\Controller\\TrustController', 'report'], true);
$router->post('/api/trust/block', ['App\\Controller\\TrustController', 'block'], true);
$router->post('/api/trust/unblock', ['App\\Controller\\TrustController', 'unblock'], true);

try {
    $router->dispatch();
} catch (\Throwable $e) {
    error_log('[FATAL] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::json(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
