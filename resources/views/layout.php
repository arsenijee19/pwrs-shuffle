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
    <nav class="desktop-nav" aria-label="Glavna navigacija">
      <a href="/faq"<?= $navActive('/faq') ?>>FAQ</a>
      <a href="/fair-play"<?= $navActive('/fair-play') ?>>Fair Play</a>
      <a href="/verify"<?= $navActive('/verify') ?>>Provera</a>
      <?php if ($isAdmin): ?>
        <a href="/admin"<?= $onAdminPage ? ' class="is-active" aria-current="location"' : '' ?>>Admin</a>
        <form method="post" action="/admin/logout" class="inline"><?= csrf_field() ?><button type="submit">Odjava</button></form>
      <?php endif; ?>
    </nav>
    <button class="nav-toggle" type="button" aria-label="Otvorite meni" aria-expanded="false" aria-controls="site-drawer" data-nav-toggle>
      <span class="nav-toggle-bars" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>
  </header>
  <div class="drawer" id="site-drawer" data-drawer aria-hidden="true">
    <div class="drawer-backdrop" data-drawer-close></div>
    <aside class="drawer-panel" role="dialog" aria-modal="true" aria-label="Meni">
      <div class="drawer-head">
        <span class="drawer-brand">PlayWorld <span>Mystery Box</span></span>
        <button class="drawer-close" type="button" aria-label="Zatvorite meni" data-drawer-close>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
        </button>
      </div>
      <div class="drawer-links">
        <a href="/"<?= $path === '/' ? ' class="is-active" aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.500 12 5l8 6.500V19a1 1 0 0 1-1 1h-4.500v-5h-5v5H5a1 1 0 0 1-1-1v-7.500Z" fill="none" stroke="currentColor" stroke-width="1.900" stroke-linejoin="round"/></svg>
          <span>Početna</span>
        </a>
        <a href="/faq"<?= $navActive('/faq') ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.500" fill="none" stroke="currentColor" stroke-width="1.900"/><path d="M9.700 9.600a2.400 2.400 0 1 1 3.500 2.100c-.7.400-1.200.9-1.200 1.700M12 16.600h.01" fill="none" stroke="currentColor" stroke-width="1.900" stroke-linecap="round"/></svg>
          <span>Česta pitanja</span>
        </a>
        <a href="/fair-play"<?= $navActive('/fair-play') ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.500 5 6.200v5.300c0 4.200 2.900 7.700 7 9 4.100-1.300 7-4.800 7-9V6.200l-7-2.700Z" fill="none" stroke="currentColor" stroke-width="1.900" stroke-linejoin="round"/><path d="m9 12 2.200 2.200L15.200 10" fill="none" stroke="currentColor" stroke-width="1.900" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Fair Play</span>
        </a>
        <a href="/verify"<?= $navActive('/verify') ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.500" fill="none" stroke="currentColor" stroke-width="1.900"/><path d="m16 16 4 4" fill="none" stroke="currentColor" stroke-width="1.900" stroke-linecap="round"/></svg>
          <span>Provera rezultata</span>
        </a>
        <a href="mailto:usluge@playworld.rs">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.500" y="5.500" width="17" height="13" rx="2.500" fill="none" stroke="currentColor" stroke-width="1.900"/><path d="m5 8 7 5.500L19 8" fill="none" stroke="currentColor" stroke-width="1.900" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Kontakt</span>
        </a>
        <?php if ($isAdmin): ?>
          <a href="/admin"<?= $onAdminPage ? ' class="is-active" aria-current="location"' : '' ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.500" fill="none" stroke="currentColor" stroke-width="1.900"/><rect x="13" y="4" width="7" height="7" rx="1.500" fill="none" stroke="currentColor" stroke-width="1.900"/><rect x="4" y="13" width="7" height="7" rx="1.500" fill="none" stroke="currentColor" stroke-width="1.900"/><rect x="13" y="13" width="7" height="7" rx="1.500" fill="none" stroke="currentColor" stroke-width="1.900"/></svg>
            <span>Admin panel</span>
          </a>
        <?php endif; ?>
      </div>
      <div class="drawer-foot">
        <a class="primary drawer-cta" href="https://www.playworld.rs/kupi/playworld-mystery-box-1004" target="_blank" rel="noopener">Kupite Mystery Box</a>
        <?php if ($isAdmin): ?>
          <form method="post" action="/admin/logout"><?= csrf_field() ?><button class="ghost drawer-logout" type="submit">Odjavite se</button></form>
        <?php endif; ?>
        <p>usluge@playworld.rs</p>
      </div>
    </aside>
  </div>
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
  <footer class="site-footer">
    <div class="site-footer-inner">
      <p class="site-footer-brand">PlayWorld <span>Mystery Box</span></p>
      <p class="site-footer-note">Mystery Box je proizvod iznenađenja. Rezultat određuje server, a nagrade imaju različite vrednosti.</p>
      <div class="site-footer-links">
        <a href="/faq">FAQ</a>
        <a href="/fair-play">Fair Play</a>
        <a href="/verify">Provera rezultata</a>
        <a href="mailto:usluge@playworld.rs">usluge@playworld.rs</a>
        <?php if (!$isAdmin): ?><a href="/admin/login">Admin</a><?php endif; ?>
      </div>
    </div>
  </footer>
</body>
</html>
