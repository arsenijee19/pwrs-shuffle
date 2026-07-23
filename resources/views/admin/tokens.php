<section class="panel">
  <div class="section-head">
    <h1>Tokeni</h1>
    <a class="ghost" href="/admin/tokens.csv">Export CSV</a>
  </div>
  <form method="post" action="/admin/tokens/generate" class="inline-tools">
    <?= csrf_field() ?>
    <label>Broj tokena<input type="number" min="1" max="500" name="count" value="1"></label>
    <button class="primary">Generiši</button>
  </form>
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
