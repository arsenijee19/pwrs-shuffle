<?php
/** @var string $viewFile */
$assetVersion = static function (string $asset): string {
  $path = __DIR__ . '/../../public/' . ltrim($asset, '/');
  $modified = @filemtime($path);
  return $modified === false ? '1' : (string) $modified;
};
$path = current_path();
$isAdmin = \PlayWorld\MysteryBox\Services\AuthService::check();
$onAdminPage = $isAdmin && str_starts_with($path, '/admin');
$navActive = static fn (string $prefix): string => $path === $prefix || str_starts_with($path, $prefix . '/') ? ' class="is-active" aria-current="page"' : '';
$adminLinks = [
    ['/admin', 'Dashboard', true],
    ['/admin/tokens', 'Tokeni', false],
    ['/admin/prizes', 'Nagrade', false],
    ['/admin/discount-codes', 'Kodovi', false],
    ['/admin/reward-deliveries', 'Bonus isporuke', false],
    ['/admin/reward-claims', 'Zahtevi', false],
    ['/admin/slanja', 'Slanja', false],
    ['/admin/fulfillments', 'n8n isporuke', false],
    ['/admin/email', 'Email', false],
    ['/admin/api', 'API / n8n', false],
    ['/admin/password', 'Lozinka', false],
];
?>
<!doctype html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? 'PlayWorld Mystery Box') . ' | PlayWorld') ?></title>
  <link rel="stylesheet" href="/assets/app.css?v=<?= e($assetVersion('assets/app.css')) ?>">
  <script defer src="/assets/app.js?v=<?= e($assetVersion('assets/app.js')) ?>"></script>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="/">PlayWorld <span>Mystery Box</span></a>
    <nav aria-label="Glavna navigacija">
      <a href="/faq"<?= $navActive('/faq') ?>>FAQ</a>
      <a href="/fair-play"<?= $navActive('/fair-play') ?>>Fair Play</a>
      <a href="/verify"<?= $navActive('/verify') ?>>Provera</a>
      <?php if ($isAdmin): ?>
        <a href="/admin"<?= $onAdminPage ? ' class="is-active" aria-current="location"' : '' ?>>Admin</a>
        <form method="post" action="/admin/logout" class="inline"><?= csrf_field() ?><button>Logout</button></form>
      <?php else: ?>
        <a href="/admin/login"<?= $navActive('/admin/login') ?>>Admin</a>
      <?php endif; ?>
    </nav>
  </header>
  <main>
    <?php if ($onAdminPage): ?>
      <nav class="admin-subnav" aria-label="Admin sekcije">
        <?php foreach ($adminLinks as [$href, $label, $exact]): ?>
          <?php $active = $exact ? $path === $href : ($path === $href || str_starts_with($path, $href . '/') || ($href === '/admin/slanja' && $path === '/admin/email-history')); ?>
          <a href="<?= e($href) ?>"<?= $active ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <?php require $viewFile; ?>
  </main>
</body>
</html>
