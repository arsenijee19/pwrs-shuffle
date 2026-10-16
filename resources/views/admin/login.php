<section class="panel narrow login-panel">
  <p class="eyebrow">Admin</p>
  <h1>Prijava</h1>
  <p class="lead">Prijavite se da upravljate Mystery Box nagradama i linkovima.</p>
  <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required autocomplete="username" inputmode="email" autofocus></label>
    <label>Lozinka<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="primary" type="submit">Prijavi se</button>
  </form>
</section>
