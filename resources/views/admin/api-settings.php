<section class="panel readable">
  <div class="section-head">
    <div>
      <p class="eyebrow">Integracije</p>
      <h1>API podešavanja</h1>
      <p class="lead">Omogućite bezbednu vezu sa n8n automatizacijom i sačuvajte pristupni ključ direktno iz ovog panela. Ključ se šifruje pre upisa u lokalnu bazu i nikada se ponovo ne prikazuje na ekranu.</p>
    </div>
    <div class="actions">
      <a class="ghost" href="/admin">Dashboard</a>
      <a class="ghost" href="/admin/fulfillments">n8n isporuke</a>
    </div>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="flash flash-<?= e((string) $flashType) ?>"><?= e((string) $flash) ?></div>
  <?php endif; ?>

  <form method="post" action="/admin/api" class="form api-settings-form">
    <?= csrf_field() ?>
    <label class="checkbox api-enable-row">
      <input type="checkbox" name="api_enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?> />
      <span><strong>Uključi API</strong><small>Dozvoli autorizovane n8n zahteve za kreiranje Mystery Box linkova.</small></span>
    </label>

    <div class="api-setting-grid">
      <label>n8n API key
        <input type="password" name="n8n_api_key" autocomplete="new-password" placeholder="<?= !empty($settings['key_saved']) ? 'Ključ je sačuvan; ostavite prazno' : 'Unesite dugačak tajni ključ' ?>" />
        <span><?= !empty($settings['key_saved']) ? 'Ključ je sačuvan. Unesite novi samo ako ga menjate.' : 'Koristite najmanje 24 karaktera i isti ključ unesite u n8n.' ?></span>
      </label>
      <label>Maksimalan broj boxova po zahtevu
        <input type="number" name="n8n_api_max_quantity" min="1" max="500" value="<?= e((string) ($settings['max_quantity'] ?? 20)) ?>" />
        <span>Zaštita od prevelikog zahteva.</span>
      </label>
      <label>Limit API zahteva po minutu
        <input type="number" name="n8n_api_rate_limit_per_minute" min="1" max="1000" value="<?= e((string) ($settings['rate_limit_per_minute'] ?? 30)) ?>" />
        <span>Limit po IP adresi.</span>
      </label>
    </div>

    <div class="notice">API podešavanja ne utiču na Brevo email slanje. Email se podešava odvojeno na stranici <a class="text-link" href="/admin/email">Admin &gt; Email</a>.</div>
    <button class="primary" type="submit">Sačuvaj API podešavanja</button>
  </form>
</section>
