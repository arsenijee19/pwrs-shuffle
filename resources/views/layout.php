<?php
/** @var string $viewFile */
$assetVersion = static function (string $asset): string {
  $path = __DIR__ . '/../../public/' . ltrim($asset, '/');
  $modified = @filemtime($path);
  return $modified === false ? '1' : (string) $modified;
};
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
    <nav>
      <a href="/faq">FAQ</a>
      <a href="/fair-play">Fair Play</a>
      <a href="/verify">Provera</a>
      <?php if (\PlayWorld\MysteryBox\Services\AuthService::check()): ?>
        <a href="/admin">Admin</a>
        <form method="post" action="/admin/logout" class="inline"><?= csrf_field() ?><button>Logout</button></form>
      <?php else: ?>
        <a href="/admin/login">Admin</a>
      <?php endif; ?>
    </nav>
  </header>
  <main>
    <?php require $viewFile; ?>
  </main>
</body>
</html>
