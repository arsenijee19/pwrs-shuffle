<section class="panel narrow">
  <p class="eyebrow">Prvo podešavanje</p>
  <h1>Kreiraj admin nalog</h1>
  <p class="lead">Ovaj ekran se prikazuje samo dok aplikacija nema nijedan admin nalog.</p>
  <?php if (!empty($error)): ?><p class="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" required autocomplete="username" value="admin@playworld.rs"></label>
    <label>Lozinka<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <button class="primary">Kreiraj admin nalog</button>
  </form>
</section>
