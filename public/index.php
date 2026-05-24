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
