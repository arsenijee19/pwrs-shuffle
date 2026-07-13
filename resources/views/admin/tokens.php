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
      <h2>Novi linkovi</h2>
      <?php foreach ($created as $item): ?>
        <div class="copy-row"><input readonly value="<?= e($item['link']) ?>"><button type="button" data-copy="<?= e($item['link']) ?>">Kopiraj</button></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <table>
    <thead><tr><th>ID</th><th>Status</th><th>Kreiran</th><th>Iskorišćen</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($tokens as $token): ?>
        <tr>
          <td><?= e((string) $token['id']) ?></td>
          <td><?= e($token['status']) ?></td>
          <td><?= e(format_date($token['created_at'])) ?></td>
          <td><?= e($token['used_at'] ? format_date($token['used_at']) : '-') ?></td>
          <td>
            <?php if ($token['status'] === 'Active'): ?>
              <form method="post" action="/admin/tokens/<?= e((string) $token['id']) ?>/revoke" class="inline"><?= csrf_field() ?><button class="danger">Revoked</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
