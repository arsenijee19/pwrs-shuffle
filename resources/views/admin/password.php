<section class="panel narrow">
  <h1>Promena lozinke</h1>
  <?php if (!empty($message)): ?><p class="notice"><?= e($message) ?></p><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Trenutna lozinka<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label>Nova lozinka<input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
    <button class="primary">Promeni lozinku</button>
  </form>
</section>
