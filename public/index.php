<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PlayWorld\MysteryBox\Middleware\RateLimiter;
use PlayWorld\MysteryBox\Services\AuthService;
use PlayWorld\MysteryBox\Services\DrawService;
use PlayWorld\MysteryBox\Services\MysteryBoxFulfillmentService;
use PlayWorld\MysteryBox\Services\N8nMysteryBoxApi;
use PlayWorld\MysteryBox\Services\PdfService;
use PlayWorld\MysteryBox\Services\PrizeService;
use PlayWorld\MysteryBox\Services\SettingsService;
use PlayWorld\MysteryBox\Services\TokenService;
use PlayWorld\MysteryBox\Support\App;
use PlayWorld\MysteryBox\Support\Database;
use PlayWorld\MysteryBox\Support\Router;

App::bootstrap(dirname(__DIR__));
Database::migrate();
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$queryRoute = (string) ($_GET['route'] ?? '');
$isApiRequest = str_starts_with($requestPath, '/api/') || str_starts_with($queryRoute, '/api/');
RateLimiter::hit('global', $isApiRequest);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if ((bool) env_value('SESSION_SECURE', true)) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$router = new Router();

$router->post('/api/v1/mystery-box/fulfill', function (): void {
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    RateLimiter::hit('n8n-api', true, (int) env_value('N8N_API_RATE_LIMIT_PER_MINUTE', 30));

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!is_array($headers)) {
        $headers = [];
    }
    if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!isset($headers['Content-Type']) && isset($_SERVER['CONTENT_TYPE'])) {
        $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
    }

    $response = (new N8nMysteryBoxApi())->handle(
        $_SERVER['REQUEST_METHOD'] ?? 'POST',
        array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $headers),
        (string) file_get_contents('php://input')
    );

    http_response_code($response['status']);
    echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

$router->get('/', fn () => render('public/home', [
    'title' => 'PlayWorld Mystery Box',
    'availablePrizes' => (new PrizeService())->activeAvailable(),
]));
$router->get('/faq', fn () => render('public/faq', ['title' => 'FAQ']));
$router->get('/fair-play', fn () => render('public/fair-play', ['title' => 'Fair Play']));
$router->get('/verify', function (): void {
    $drawId = strtoupper(trim((string) ($_GET['draw'] ?? '')));
    $draw = $drawId !== '' ? (new DrawService())->findByDrawId($drawId) : null;

    render('public/verify', [
        'title' => 'Provera rezultata',
        'drawId' => $drawId,
        'draw' => $draw,
        'searched' => $drawId !== '',
    ], $drawId !== '' && !$draw ? 404 : 200);
});

$router->get('/setup', function (): void {
    if (Database::hasAdmins()) {
        redirect('/admin/login');
    }

    render('admin/setup', ['title' => 'Initial Setup']);
});
$router->post('/setup', function (): void {
    verify_csrf();
    if (Database::hasAdmins()) {
        redirect('/admin/login');
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        render('admin/setup', [
            'title' => 'Initial Setup',
            'error' => 'Unesite ispravan email i lozinku od najmanje 10 karaktera.',
        ], 422);
        return;
    }

    (new AuthService())->createInitialAdmin($email, $password);
    redirect('/admin/login');
});

$router->get('/admin/login', function (): void {
    if (!Database::hasAdmins()) {
        redirect('/setup');
    }

    render('admin/login', ['title' => 'Admin Login']);
});
$router->post('/admin/login', function (): void {
    verify_csrf();
    RateLimiter::hit('admin-login');
    if ((new AuthService())->attempt($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect('/admin');
    }
    render('admin/login', ['title' => 'Admin Login', 'error' => 'Neispravni podaci za prijavu.'], 422);
});
$router->post('/admin/logout', function (): void {
    verify_csrf();
    (new AuthService())->logout();
    redirect('/admin/login');
});

$router->get('/admin', function (): void {
    AuthService::requireAdmin();
    $tokens = new TokenService();
    $draws = new DrawService();
    render('admin/dashboard', [
        'title' => 'Admin Dashboard',
        'stats' => $tokens->stats(),
        'recent' => $draws->recent(),
        'counts' => $draws->prizeCounts(),
    ]);
});

$router->get('/admin/prizes', function (): void {
    AuthService::requireAdmin();
    render('admin/prizes', ['title' => 'Nagrade', 'prizes' => (new PrizeService())->all()]);
});
$router->get('/admin/prizes/new', function (): void {
    AuthService::requireAdmin();
    render('admin/prize-form', ['title' => 'Nova nagrada', 'prize' => null]);
});
$router->get('/admin/prizes/{id}/edit', function (string $id): void {
    AuthService::requireAdmin();
    render('admin/prize-form', ['title' => 'Izmena nagrade', 'prize' => (new PrizeService())->find((int) $id)]);
});
$router->post('/admin/prizes/save', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    (new PrizeService())->save($_POST, $_FILES['cover'] ?? null);
    redirect('/admin/prizes');
});
$router->post('/admin/prizes/seed-playworld', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    $_SESSION['flash'] = (new PrizeService())->seedPlayWorldCatalog();
    redirect('/admin/prizes');
});
$router->post('/admin/prizes/{id}/delete', function (string $id): void {
    AuthService::requireAdmin();
    verify_csrf();
    (new PrizeService())->delete((int) $id);
    redirect('/admin/prizes');
});

$router->get('/admin/tokens', function (): void {
    AuthService::requireAdmin();
    render('admin/tokens', [
        'title' => 'Tokeni',
        'tokens' => (new TokenService())->list(),
        'created' => $_SESSION['created_tokens'] ?? [],
        'flash' => $_SESSION['flash'] ?? null,
        'flashType' => $_SESSION['flash_type'] ?? 'success',
    ]);
    unset($_SESSION['created_tokens']);
    unset($_SESSION['flash'], $_SESSION['flash_type']);
});
$router->get('/admin/fulfillments', function (): void {
    AuthService::requireAdmin();
    render('admin/fulfillments', [
        'title' => 'n8n isporuke',
        'fulfillments' => (new MysteryBoxFulfillmentService())->recent(),
    ]);
});
$router->post('/admin/tokens/generate', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    $count = min(500, max(1, (int) ($_POST['count'] ?? 1)));
    $_SESSION['created_tokens'] = (new TokenService())->generate($count);
    redirect('/admin/tokens');
});
$router->post('/admin/tokens/send-email', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    RateLimiter::hit('admin-send-email');

    $email = trim((string) ($_POST['email'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = 'Unesite ispravan email kupca.';
        $_SESSION['flash_type'] = 'error';
        redirect('/admin/tokens');
    }

    try {
        $created = [(new MysteryBoxFulfillmentService())->createAndSend($email, $name)];
        $_SESSION['created_tokens'] = $created;
        $_SESSION['flash'] = 'Mystery Box link je kreiran i poslat na ' . $email . '.';
        $_SESSION['flash_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['flash'] = 'Link je kreiran, ali email nije poslat: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }

    redirect('/admin/tokens');
});
$router->post('/admin/tokens/{id}/revoke', function (string $id): void {
    AuthService::requireAdmin();
    verify_csrf();
    (new TokenService())->revoke((int) $id);
    redirect('/admin/tokens');
});
$router->get('/admin/tokens.csv', function (): void {
    AuthService::requireAdmin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tokens.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'status', 'created_at', 'used_at']);
    foreach ((new TokenService())->list(10000) as $token) {
        fputcsv($out, [$token['id'], $token['status'], $token['created_at'], $token['used_at']]);
    }
});
$router->get('/admin/email', function (): void {
    AuthService::requireAdmin();
    $settings = new SettingsService();
    render('admin/email-settings', [
        'title' => 'Email podešavanja',
        'settings' => $settings->all(),
        'passwordSaved' => $settings->get('smtp_password') !== '',
        'flash' => $_SESSION['flash'] ?? null,
        'flashType' => $_SESSION['flash_type'] ?? 'success',
    ]);
    unset($_SESSION['flash'], $_SESSION['flash_type']);
});
$router->get('/admin/email-history', function (): void {
    AuthService::requireAdmin();
    $deliveries = new EmailDeliveryService();
    render('admin/email-history', [
        'title' => 'Istorija slanja',
        'deliveries' => $deliveries->recent(),
        'stats' => $deliveries->stats(),
    ]);
});
$router->post('/admin/email', function (): void {
    AuthService::requireAdmin();
    verify_csrf();

    $email = trim((string) ($_POST['mail_from'] ?? ''));
    $username = trim((string) ($_POST['smtp_username'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $username === '') {
        $_SESSION['flash'] = 'Unesite ispravan email pošiljaoca i SMTP korisničko ime.';
        $_SESSION['flash_type'] = 'error';
        redirect('/admin/email');
    }

    (new SettingsService())->saveEmailSettings($_POST);
    $_SESSION['flash'] = 'Email podešavanja su sačuvana.';
    $_SESSION['flash_type'] = 'success';
    redirect('/admin/email');
});
$router->get('/admin/password', function (): void {
    AuthService::requireAdmin();
    render('admin/password', ['title' => 'Promena lozinke']);
});
$router->post('/admin/password', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    $ok = (new AuthService())->changePassword($_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
    render('admin/password', ['title' => 'Promena lozinke', 'message' => $ok ? 'Lozinka je promenjena.' : 'Trenutna lozinka nije ispravna.'], $ok ? 200 : 422);
});

$router->get('/box/{token}', function (string $plain): void {
    $tokens = new TokenService();
    $plain = $tokens->normalizePlain($plain);
    $token = $tokens->findPlain($plain);
    if (!$token) {
        render('public/invalid', ['title' => 'Nevažeći link', 'reason' => 'Ovaj Mystery Box link nije pronađen.'], 404);
        return;
    }
    $draw = (new DrawService())->resultForToken((int) $token['id']);
    if ($draw) {
        if (!(new DrawService())->isVisible($draw)) {
            render('public/invalid', ['title' => 'Rezultat je istekao', 'reason' => 'Rezultat je bio dostupan 5 dana nakon otvaranja.'], 410);
            return;
        }
        render('public/result', ['title' => 'Rezultat', 'draw' => $draw, 'token' => $plain]);
        return;
    }
    if ($token['status'] !== 'Active') {
        render('public/invalid', ['title' => 'Token nije aktivan', 'reason' => 'Ovaj token je ' . strtolower($token['status']) . '.'], 410);
        return;
    }
    render('public/box', [
        'title' => 'Open Mystery Box',
        'token' => $plain,
        'availablePrizes' => (new PrizeService())->activeAvailable(),
    ]);
});
$router->post('/box/{token}/open', function (string $plain): void {
    verify_csrf();
    $plain = (new TokenService())->normalizePlain($plain);
    try {
        $draw = (new DrawService())->open($plain);
        render('public/result', ['title' => 'Congratulations', 'draw' => $draw, 'token' => $plain, 'openedNow' => true]);
    } catch (Throwable $e) {
        render('public/invalid', ['title' => 'Mystery Box nije dostupan', 'reason' => $e->getMessage()], 422);
    }
});
$router->get('/box/{token}/certificate', function (string $plain): void {
    $tokens = new TokenService();
    $plain = $tokens->normalizePlain($plain);
    $token = $tokens->findPlain($plain);
    if (!$token) {
        render('public/invalid', ['title' => 'Nevažeći link', 'reason' => 'Token nije pronađen.'], 404);
        return;
    }
    $draw = (new DrawService())->resultForToken((int) $token['id']);
    if (!$draw || !(new DrawService())->isVisible($draw)) {
        render('public/invalid', ['title' => 'PDF nije dostupan', 'reason' => 'Rezultat nije dostupan.'], 410);
        return;
    }
    (new PdfService())->output($draw, url('/box/' . $plain));
});

$router->dispatch();
