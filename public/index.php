<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PlayWorld\MysteryBox\Middleware\RateLimiter;
use PlayWorld\MysteryBox\Services\AuthService;
use PlayWorld\MysteryBox\Services\DrawService;
use PlayWorld\MysteryBox\Services\MysteryBoxFulfillmentService;
use PlayWorld\MysteryBox\Services\MailService;
use PlayWorld\MysteryBox\Services\N8nMysteryBoxApi;
use PlayWorld\MysteryBox\Services\N8nRewardClaimsApi;
use PlayWorld\MysteryBox\Services\DiscountCodeService;
use PlayWorld\MysteryBox\Services\PdfService;
use PlayWorld\MysteryBox\Services\PrizeService;
use PlayWorld\MysteryBox\Services\RewardClaimService;
use PlayWorld\MysteryBox\Services\RewardDeliveryService;
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
if (str_starts_with($requestPath, '/admin') || str_starts_with($requestPath, '/box/')) {
    header('Cache-Control: no-store, private');
}
if ((bool) env_value('SESSION_SECURE', true)) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$router = new Router();

$apiHeaders = static function (): array {
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
    return array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $headers);
};

$router->post('/api/v1/mystery-box/fulfill', function () use ($apiHeaders): void {
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    RateLimiter::hit('n8n-api', true, (new SettingsService())->apiConfig()['rate_limit_per_minute']);

    $response = (new N8nMysteryBoxApi())->handle(
        $_SERVER['REQUEST_METHOD'] ?? 'POST',
        $apiHeaders(),
        (string) file_get_contents('php://input')
    );

    http_response_code($response['status']);
    echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});
$router->post('/api/v1/reward-claims/lease', function () use ($apiHeaders): void {
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    RateLimiter::hit('n8n-reward-api', true, (new SettingsService())->apiConfig()['rate_limit_per_minute']);
    $response = (new N8nRewardClaimsApi())->lease($_SERVER['REQUEST_METHOD'] ?? 'POST', $apiHeaders(), (string) file_get_contents('php://input'));
    http_response_code($response['status']);
    echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});
$router->post('/api/v1/reward-claims/{id}/notification-result', function (string $id) use ($apiHeaders): void {
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    RateLimiter::hit('n8n-reward-api', true, (new SettingsService())->apiConfig()['rate_limit_per_minute']);
    $response = (new N8nRewardClaimsApi())->notificationResult((int) $id, $_SERVER['REQUEST_METHOD'] ?? 'POST', $apiHeaders(), (string) file_get_contents('php://input'));
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
    try {
        (new PrizeService())->save($_POST, $_FILES['cover'] ?? null);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Nagrada je sačuvana.'];
    } catch (Throwable $error) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $error->getMessage()];
    }
    redirect('/admin/prizes');
});
$router->post('/admin/prizes/{id}/inline', function (string $id): void {
    AuthService::requireAdmin();
    verify_csrf();
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    try {
        $prize = (new PrizeService())->inlineUpdate((int) $id, trim((string) ($_POST['field'] ?? '')), trim((string) ($_POST['value'] ?? '')));
        echo json_encode(['ok' => true, 'prize' => $prize], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});
$router->post('/admin/prizes/seed-playworld', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    $_SESSION['flash'] = (new PrizeService())->seedPlayWorldCatalog();
    redirect('/admin/prizes');
});
$router->post('/admin/prizes/seed-bonus', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    $_SESSION['flash'] = (new PrizeService())->seedBonusCatalog();
    redirect('/admin/prizes');
});
$router->post('/admin/prizes/{id}/toggle', function (string $id): void {
    AuthService::requireAdmin();
    verify_csrf();
    (new PrizeService())->toggle((int) $id);
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
$router->post('/admin/fulfillments/{id}/cancel', function (string $id): void {
    AuthService::requireAdmin();
    verify_csrf();
    RateLimiter::hit('admin-fulfillment-cancel');

    try {
        $result = (new MysteryBoxFulfillmentService())->cancel((int) $id);
        if ($result['already_cancelled']) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Ova n8n isporuka je već poništena.'];
        } else {
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Isporuka je poništena. Opozvano aktivnih linkova: ' . $result['revoked_quantity'] . '. Već iskorišćeno: ' . $result['used_quantity'] . '.',
            ];
        }
    } catch (Throwable $error) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Poništavanje nije uspelo: ' . $error->getMessage()];
    }

    redirect('/admin/fulfillments');
});
$router->get('/admin/discount-codes', function (): void {
    AuthService::requireAdmin();
    $search = trim((string) ($_GET['q'] ?? ''));
    $percentage = (int) ($_GET['percentage'] ?? 0);
    $status = trim((string) ($_GET['status'] ?? ''));
    $discountCodes = new DiscountCodeService();
    $stats = $discountCodes->stats();
    $totals = ['available' => 0, 'assigned' => 0, 'total' => 0];
    foreach ($stats as $stat) {
        $totals['available'] += (int) $stat['available'];
        $totals['assigned'] += (int) $stat['assigned'];
    }
    $totals['total'] = $totals['available'] + $totals['assigned'];
    render('admin/discount-codes', [
        'title' => 'Kodovi za popust',
        'codes' => $discountCodes->recent(500, $search, $percentage > 0 ? $percentage : null, $status !== '' ? $status : null),
        'stats' => $stats,
        'totals' => $totals,
        'filters' => compact('search', 'percentage', 'status'),
    ]);
});
$router->get('/admin/discount-codes/export', function (): void {
    AuthService::requireAdmin();

    try {
        $contents = (new DiscountCodeService())->exportGroupedText();
    } catch (Throwable $error) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Izvoz promo kodova nije uspeo. Proverite APP_KEY i zaštićene podatke.'];
        redirect('/admin/discount-codes');
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="playworld-promo-kodovi-' . date('Y-m-d') . '.txt"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo $contents;
});
$router->post('/admin/discount-codes/import', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    try {
        $result = (new DiscountCodeService())->import((string) ($_POST['codes'] ?? ''), (int) ($_POST['percentage'] ?? 0), (int) $_SESSION['admin_id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Uvezeno: {$result['created']}; duplikati: {$result['duplicates']}; nevalidni: {$result['invalid']}." ];
    } catch (Throwable $error) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $error->getMessage()];
    }
    redirect('/admin/discount-codes');
});
$router->get('/admin/reward-deliveries', function (): void {
    AuthService::requireAdmin();
    render('admin/reward-deliveries', ['title' => 'Bonus isporuke', 'deliveries' => (new RewardDeliveryService())->recent()]);
});
$router->post('/admin/reward-deliveries/{id}/resend', function (string $id): void {
    AuthService::requireAdmin();
    verify_csrf();
    RateLimiter::hit('admin-reward-resend');

    try {
        $result = (new RewardDeliveryService())->resendExisting((int) $id);
        if ($result['failed_quantity'] === 0) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => $result['sent_quantity'] . ' postojeća bonus linka poslata su na ' . $result['recipient_email'] . '.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Poslato: ' . $result['sent_quantity'] . '/' . $result['requested_quantity'] . '. ' . $result['failed_quantity'] . ' linkova nije poslato.'];
        }
    } catch (Throwable $error) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Ponovno slanje nije uspelo: ' . $error->getMessage()];
    }

    redirect('/admin/reward-deliveries');
});
$router->get('/admin/reward-claims', function (): void {
    AuthService::requireAdmin();
    render('admin/reward-claims', ['title' => 'Zahtevi za nagrade', 'claims' => (new RewardClaimService())->recent()]);
});
$router->get('/admin/reward-claims/{id}', function (string $id): void {
    AuthService::requireAdmin();
    $claim = (new RewardClaimService())->findById((int) $id);
    render('admin/reward-claim', ['title' => 'Zahtev za nagradu', 'claim' => $claim], $claim ? 200 : 404);
});
$router->post('/admin/reward-claims/{id}/status', function (string $id): void {
    AuthService::requireAdmin();
    verify_csrf();
    (new RewardClaimService())->updateStatus((int) $id, (string) ($_POST['status'] ?? ''));
    redirect('/admin/reward-claims/' . (int) $id);
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
$router->get('/admin/api', function (): void {
    AuthService::requireAdmin();
    $settings = new SettingsService();
    render('admin/api-settings', [
        'title' => 'API podešavanja',
        'settings' => $settings->apiSettingsForAdmin(),
        'flash' => $_SESSION['flash'] ?? null,
        'flashType' => $_SESSION['flash_type'] ?? 'success',
    ]);
    unset($_SESSION['flash'], $_SESSION['flash_type']);
});
$router->post('/admin/api', function (): void {
    AuthService::requireAdmin();
    verify_csrf();

    try {
        (new SettingsService())->saveApiSettings($_POST);
        $_SESSION['flash'] = 'API podešavanja su sačuvana.';
        $_SESSION['flash_type'] = 'success';
    } catch (Throwable $error) {
        $_SESSION['flash'] = $error->getMessage();
        $_SESSION['flash_type'] = 'error';
    }

    redirect('/admin/api');
});
$emailHistoryPage = static function (): void {
    AuthService::requireAdmin();
    $deliveries = new EmailDeliveryService();
    render('admin/email-history', [
        'title' => 'Istorija slanja',
        'deliveries' => $deliveries->recent(),
        'stats' => $deliveries->stats(),
    ]);
};
$router->get('/admin/email-history', $emailHistoryPage);
$router->get('/admin/slanja', $emailHistoryPage);
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
$router->post('/admin/email/test', function (): void {
    AuthService::requireAdmin();
    verify_csrf();
    RateLimiter::hit('admin-email-test');

    $email = trim((string) ($_POST['test_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = 'Unesite ispravan email za test.';
        $_SESSION['flash_type'] = 'error';
        redirect('/admin/email');
    }

    try {
        (new MailService())->sendTestEmail($email);
        $_SESSION['flash'] = 'Test email je poslat na ' . $email . '.';
        $_SESSION['flash_type'] = 'success';
    } catch (Throwable $error) {
        $_SESSION['flash'] = 'Test email nije poslat: ' . $error->getMessage();
        $_SESSION['flash_type'] = 'error';
    }

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
        render('public/result', [
            'title' => 'Rezultat', 'draw' => $draw, 'token' => $plain,
            'discountCode' => (new DiscountCodeService())->forDraw((string) $draw['draw_id']),
            'bonusDelivery' => (new RewardDeliveryService())->forDraw((string) $draw['draw_id']),
            'rewardClaim' => (new RewardClaimService())->forDraw((string) $draw['draw_id']),
        ]);
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
        render('public/result', [
            'title' => 'Congratulations', 'draw' => $draw, 'token' => $plain, 'openedNow' => true,
            'discountCode' => (new DiscountCodeService())->forDraw((string) $draw['draw_id']),
            'bonusDelivery' => (new RewardDeliveryService())->forDraw((string) $draw['draw_id']),
            'rewardClaim' => (new RewardClaimService())->forDraw((string) $draw['draw_id']),
        ]);
    } catch (Throwable $e) {
        $known = [
            'Token nije pronađen.', 'Token nije aktivan.', 'Token je istekao.',
            'Trenutno nema dostupnih nagrada.', 'Nagrada više nije dostupna.',
            'Kod za popust više nije dostupan.',
        ];
        $reason = in_array($e->getMessage(), $known, true)
            ? $e->getMessage()
            : 'Otvaranje trenutno nije moguće. Molimo osvežite stranicu ili kontaktirajte PlayWorld tim.';
        render('public/invalid', ['title' => 'Mystery Box nije dostupan', 'reason' => $reason], 422);
    }
});
$router->post('/box/{token}/bonus', function (string $plain): void {
    verify_csrf();
    RateLimiter::hit('customer-bonus-claim', true, 10);
    $tokens = new TokenService();
    $plain = $tokens->normalizePlain($plain);
    $token = $tokens->findPlain($plain);
    $draw = $token ? (new DrawService())->resultForToken((int) $token['id']) : null;
    if (!$draw || !(new DrawService())->isVisible($draw)) {
        render('public/invalid', ['title' => 'Bonus nije dostupan', 'reason' => 'Rezultat nije dostupan.'], 410);
        return;
    }
    try {
        $delivery = (new RewardDeliveryService())->claim($draw, (string) ($_POST['email'] ?? ''), (string) ($_POST['name'] ?? ''));
        $_SESSION['flash'] = match ((string) ($delivery['status'] ?? '')) {
            'Sent' => 'Linkovi su poslati na ' . (string) ($delivery['masked_email'] ?? 'unetu email adresu') . '.',
            'NeedsReview' => 'Email server nije potvrdio ishod slanja. Proverite Primljeno i Spam, pa kontaktirajte PlayWorld tim ako poruka nije stigla.',
            'Sending' => 'Slanje je u toku. Osvežite stranicu za nekoliko trenutaka.',
            default => 'Slanje nije uspelo. Proverite email adresu i pokušajte ponovo; isti linkovi će biti poslati bez kreiranja novih boxova.',
        };
    } catch (Throwable $error) {
        $_SESSION['flash'] = $error->getMessage();
    }
    redirect('/box/' . rawurlencode($plain));
});
$router->post('/box/{token}/claim', function (string $plain): void {
    verify_csrf();
    RateLimiter::hit('customer-reward-claim', true, 10);
    $tokens = new TokenService();
    $plain = $tokens->normalizePlain($plain);
    $token = $tokens->findPlain($plain);
    $draw = $token ? (new DrawService())->resultForToken((int) $token['id']) : null;
    if (!$draw || !(new DrawService())->isVisible($draw)) {
        render('public/invalid', ['title' => 'Zahtev nije dostupan', 'reason' => 'Rezultat nije dostupan.'], 410);
        return;
    }
    try {
        (new RewardClaimService())->create($draw, (string) ($_POST['email'] ?? ''), (string) ($_POST['phone'] ?? ''));
        $_SESSION['flash'] = 'Zahtev je primljen. PlayWorld tim će Vam se javiti.';
    } catch (Throwable $error) {
        $_SESSION['flash'] = $error->getMessage();
    }
    redirect('/box/' . rawurlencode($plain));
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
