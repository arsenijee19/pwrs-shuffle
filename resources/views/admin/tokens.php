<section class="panel">
  <div class="section-head">
    <h1>Tokeni</h1>
    <div class="actions">
      <a class="ghost" href="/admin/email">Email podešavanja</a>
      <a class="ghost" href="/admin/tokens.csv">Export CSV</a>
    </div>
  </div>
  <?php if (!empty($flash)): ?>
    <div class="flash flash-<?= e((string) $flashType) ?>"><?= e((string) $flash) ?></div>
  <?php endif; ?>
  <div class="token-tools">
    <form method="post" action="/admin/tokens/generate" class="token-tool-card">
      <?= csrf_field() ?>
      <div>
        <h2>Generiši linkove</h2>
        <p>Koristite kada želite ručno da kopirate jedan ili više Mystery Box linkova.</p>
      </div>
      <label>Broj tokena<input type="number" min="1" max="500" name="count" value="1"></label>
      <button class="primary">Generiši</button>
    </form>
    <form method="post" action="/admin/tokens/send-email" class="token-tool-card featured-mail">
      <?= csrf_field() ?>
      <div>
        <h2>Isporuči na email</h2>
        <p>Kreira novi jedinstveni link i šalje kupcu HTML email sa dugmetom za otvaranje box-a.</p>
      </div>
      <label>Email kupca<input type="email" name="email" placeholder="kupac@example.com" required></label>
      <label>Ime kupca <span>opciono</span><input type="text" name="name" placeholder="Ime kupca"></label>
      <button class="primary">Generiši i pošalji</button>
    </form>
  </div>
  <?php if (!empty($created)): ?>
    <div class="created-links">
      <div class="section-head compact-head">
        <div>
          <h2>Novi linkovi</h2>
          <p>Svaki red ima svoj status kopiranja, da lako vidite šta ste već poslali.</p>
        </div>
        <button type="button" class="ghost" data-copy-all-formatted>Copy all formatted</button>
      </div>
      <?php foreach ($created as $item): ?>
        <div class="copy-row" data-copy-row>
          <input readonly value="<?= e($item['link']) ?>">
          <button type="button" data-copy="<?= e($item['link']) ?>">Kopiraj link</button>
          <button type="button" data-copy-formatted="<?= e($item['link']) ?>">Copy formatted</button>
          <span class="copy-status" data-copy-status>Nije kopirano</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <table>
    <thead><tr><th>ID</th><th>Status</th><th>Kreiran</th><th>Iskorišćen</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($tokens as $token): ?>
        <tr>
          <td><?= e((string) $token['id']) ?></td>
          <td><span class="status-pill status-<?= e(strtolower($token['status'])) ?>"><?= e($token['status']) ?></span></td>
          <td><?= e(format_date($token['created_at'])) ?></td>
          <td><?= e($token['used_at'] ? format_date($token['used_at']) : '-') ?></td>
          <td>
            <?php if ($token['status'] === 'Active'): ?>
              <form method="post" action="/admin/tokens/<?= e((string) $token['id']) ?>/revoke" class="inline" data-confirm-action="Poništiti ovaj link? Kupac više neće moći da ga otvori."><?= csrf_field() ?><button class="danger">Poništi link</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
