<section class="panel readable">
  <div class="section-head">
    <div>
      <p class="eyebrow">Email isporuka</p>
      <h1>Email podešavanja</h1>
      <p class="lead">Unesite Brevo SMTP podatke jednom. Nakon toga admin može direktno da šalje Mystery Box link kupcu.</p>
    </div>
    <div class="actions">
    </div>
  </div>
  <?php if (!empty($flash)): ?>
    <div class="flash flash-<?= e((string) $flashType) ?>"><?= e((string) $flash) ?></div>
  <?php endif; ?>
  <form method="post" action="/admin/email" class="form email-settings-form">
    <?= csrf_field() ?>
    <label class="checkbox">
      <input type="checkbox" name="mail_enabled" value="1" <?= (($settings['mail_enabled'] ?? 'false') === 'true') ? 'checked' : '' ?>>
      Uključi slanje emaila
    </label>
    <div class="form-grid">
      <label>SMTP server<input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? 'smtp-relay.brevo.com') ?>" required></label>
      <label>SMTP port<input type="number" min="1" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>" required></label>
    </div>
    <label>SMTP korisničko ime<input type="text" name="smtp_username" value="<?= e($settings['smtp_username'] ?? (string) env_value('SMTP_USERNAME', '')) ?>" placeholder="Brevo SMTP login" autocomplete="off" required></label>
    <label>
      SMTP key <span><?= $passwordSaved ? 'sačuvan je, ostavite prazno ako ga ne menjate' : 'nalepite ceo Brevo key koji počinje sa xsmtpsib-' ?></span>
      <input type="password" name="smtp_password" value="" autocomplete="new-password" placeholder="<?= $passwordSaved ? 'Sačuvan SMTP key' : 'xsmtpsib-...' ?>">
    </label>
    <div class="form-grid">
      <label>Email pošiljaoca<input type="email" name="mail_from" value="<?= e($settings['mail_from'] ?? 'usluge@playworld.rs') ?>" required></label>
      <label>Ime pošiljaoca<input type="text" name="mail_from_name" value="<?= e($settings['mail_from_name'] ?? 'PlayWorld - Mystery Box') ?>" required></label>
    </div>
    <label>Timeout<input type="number" min="3" name="mail_timeout" value="<?= e($settings['mail_timeout'] ?? '15') ?>"></label>
    <div class="notice">SMTP key se ne prikazuje ponovo posle čuvanja. Ako želite da ga promenite, samo nalepite novi key i sačuvajte.</div>
    <button class="primary" type="submit">Sačuvaj email podešavanja</button>
  </form>
  <section class="email-test-card">
    <div>
      <p class="eyebrow">Brza provera</p>
      <h2>Pošalji test email</h2>
      <p class="muted">Prvo sačuvajte podešavanja, zatim pošaljite test. Ovo ne kreira Mystery Box token.</p>
    </div>
    <form method="post" action="/admin/email/test" class="email-test-form">
      <?= csrf_field() ?>
      <label>Email za test<input type="email" name="test_email" value="<?= e($settings['mail_from'] ?? (string) env_value('MAIL_FROM', '')) ?>" required></label>
      <button class="ghost" type="submit">Pošalji test email</button>
    </form>
  </section>
</section>
