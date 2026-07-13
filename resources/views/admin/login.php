<section class="panel narrow">
  <p class="eyebrow">Admin</p>
  <h1>Login</h1>
  <?php if (!empty($error)): ?><p class="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required autocomplete="username"></label>
    <label>Lozinka<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="primary">Login</button>
  </form>
</section>
