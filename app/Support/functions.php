<?php

declare(strict_types=1);

use PlayWorld\MysteryBox\Support\App;

function app_path(string $path = ''): string
{
    return App::basePath($path);
}

function env_value(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => $value,
    };
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_path(): string
{
    $route = (string) ($_GET['route'] ?? '');
    if ((bool) env_value('APP_QUERY_ROUTES', false) && str_starts_with($route, '/')) {
        return rtrim($route, '/') ?: '/';
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') ?: '/';
}

function redirect(string $path): never
{
    if ((bool) env_value('APP_QUERY_ROUTES', false) && str_starts_with($path, '/')) {
        [$route, $fragment] = array_pad(explode('#', $path, 2), 2, null);
        $path = '/index.php?route=' . rawurlencode($route) . ($fragment !== null ? '#' . $fragment : '');
    }

    header('Location: ' . $path, true, 302);
    exit;
}

function url(string $path = ''): string
{
    $base = rtrim((string) env_value('APP_URL', ''), '/');
    if ($base === '' || str_contains($base, 'example') || str_contains($base, 'tvoj-domen')) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = ($https ? 'https://' : 'http://') . $host;
    }
    return $base . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        echo 'CSRF token mismatch.';
        exit;
    }
}

function render(string $view, array $data = [], int $status = 200): void
{
    http_response_code($status);
    extract($data, EXTR_SKIP);
    $viewFile = app_path('resources/views/' . $view . '.php');
    require app_path('resources/views/layout.php');
}

function format_date(string $date): string
{
    $dt = new DateTimeImmutable($date);
    return $dt->format('d.m.Y. H:i');
}

/** Digits-only international phone number (+ and 00 prefixes stripped; local 0xx numbers are assumed to be Serbian). */
function phone_international(string $phone): string
{
    $digits = preg_replace('/[^0-9+]/', '', $phone) ?: '';
    if (str_starts_with($digits, '+')) {
        return ltrim($digits, '+');
    }
    if (str_starts_with($digits, '00')) {
        return substr($digits, 2);
    }
    if (str_starts_with($digits, '0')) {
        return '381' . substr($digits, 1);
    }
    return $digits;
}

/** @return array{label:string,class:string} */
function claim_status(string $status): array
{
    return match ($status) {
        'InProgress' => ['label' => 'U toku', 'class' => 'chip-progress'],
        'Delivered' => ['label' => 'Isporučeno', 'class' => 'chip-delivered'],
        'Cancelled' => ['label' => 'Otkazano', 'class' => 'chip-cancelled'],
        default => ['label' => 'Novo', 'class' => 'chip-new'],
    };
}
