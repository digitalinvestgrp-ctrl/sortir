<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router minimaliste — match HTTP method + path pattern -> [Controller, method]
 * Support placeholders {slug}, {id} extraits en args.
 * Middleware Auth Bearer optionnel par route.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, bool $requireAuth = false): void
    {
        $this->add('GET', $path, $handler, $requireAuth);
    }

    public function post(string $path, array $handler, bool $requireAuth = false): void
    {
        $this->add('POST', $path, $handler, $requireAuth);
    }

    private function add(string $method, string $path, array $handler, bool $requireAuth): void
    {
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'auth' => $requireAuth,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            // Extract named params
            $params = [];
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $params[$k] = $v;
                }
            }

            // Auth middleware
            $user = null;
            if ($route['auth']) {
                $user = AuthMiddleware::authenticate();
                if (!$user) {
                    Response::json(['error' => 'Unauthorized'], 401);
                    return;
                }
            }

            $request = new Request($params, $user);

            [$class, $methodName] = $route['handler'];
            $controller = new $class();
            $controller->{$methodName}($request);
            return;
        }

        Response::json(['error' => 'Not found', 'path' => $uri], 404);
    }
}
