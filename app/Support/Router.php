<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Support;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';

        if ($script !== '' && str_starts_with($path, $script)) {
            $path = substr($path, strlen($script)) ?: '/';
        }

        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route) . '$#';
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler(...array_values($params));
                return;
            }
        }

        render('errors/not-found', ['title' => 'Page not found'], 404);
    }
}
