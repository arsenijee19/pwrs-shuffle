<section class="panel">
  <div class="section-head"><div><p class="eyebrow">Nagradni fond</p><h1>Kodovi za popust</h1><p class="muted">Kodovi se dodeljuju tek kada server odabere odgovarajuću nagradu.</p></div><a class="ghost" href="/admin/prizes">Nazad na nagrade</a></div>
  <div class="stats-grid compact-stats">
    <?php foreach ($stats as $stat): ?><div class="stat-card <?= $stat['available'] <= (int) env_value('DISCOUNT_LOW_STOCK_THRESHOLD', 10) ? 'low-stock' : '' ?>"><span><?= e((string) $stat['percentage']) ?>%</span><strong><?= e((string) $stat['available']) ?></strong><small>dostupno / <?= e((string) $stat['assigned']) ?> dodeljeno<?= $stat['available'] <= (int) env_value('DISCOUNT_LOW_STOCK_THRESHOLD', 10) ? ' · dopuniti' : '' ?></small></div><?php endforeach; ?>
  </div>
  <form method="post" action="/admin/discount-codes/import" class="form import-form">
    <?= csrf_field() ?>
    <h2>Uvezi kodove</h2>
    <p class="muted">Jedan kod po liniji. CSV red može imati kod u prvoj koloni.</p>
    <label>Procenat<input type="number" min="1" max="100" name="percentage" required></label>
    <label>Kodovi<textarea name="codes" rows="8" required placeholder="PW10-ABCD-EFGH&#10;PW10-IJKL-MNOP"></textarea></label>
    <button class="primary" type="submit">Uvezi kodove</button>
  </form>
</section>
<section class="panel"><div class="section-head"><h2>Istorija kodova</h2><span class="muted">Kodovi se prikazuju maskirano u admin listi.</span></div>
  <form method="get" action="/admin/discount-codes" class="filter-row"><input name="q" value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="Pretraži Draw ID ili nagradu"><input type="number" name="percentage" min="1" max="100" value="<?= e((string) ($filters['percentage'] ?? '')) ?>" placeholder="Procenat"><select name="status"><option value="">Svi statusi</option><option value="Available" <?= ($filters['status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option><option value="Assigned" <?= ($filters['status'] ?? '') === 'Assigned' ? 'selected' : '' ?>>Assigned</option></select><button class="ghost">Filtriraj</button></form>
  <table><thead><tr><th>Kod</th><th>Procenat</th><th>Status</th><th>Draw ID</th><th>Datum</th></tr></thead><tbody>
  <?php foreach ($codes as $code): ?><tr><td><code><?= e($code['masked_code']) ?></code></td><td><?= e((string) $code['percentage']) ?>%</td><td><span class="status-pill status-<?= strtolower(e($code['status'])) ?>"><?= e($code['status']) ?></span></td><td><?= e((string) ($code['assigned_draw_id'] ?? '-')) ?></td><td><?= e(format_date($code['created_at'])) ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
