<?php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashIsError = is_array($flash) && ($flash['type'] ?? '') === 'error';
$flashMessage = is_array($flash) ? (string) ($flash['message'] ?? '') : (string) $flash;
$lowStockThreshold = (int) env_value('DISCOUNT_LOW_STOCK_THRESHOLD', 10);
?>
<section class="panel discount-page">
  <div class="section-head discount-page-head">
    <div>
      <p class="eyebrow">Nagradni fond</p>
      <h1>Kodovi za popust</h1>
      <p class="lead">Ovde vidite koliko kodova je spremno za dodelu i uvozite novu zalihu bez otkrivanja kodova u javnosti.</p>
    </div>
    <div class="actions discount-page-actions">
      <a class="primary" href="/admin/discount-codes/export" download>Preuzmi sve kodove (.txt)</a>
      <a class="ghost" href="/admin/prizes">Nazad na nagrade</a>
    </div>
  </div>

  <?php if ($flashMessage !== ''): ?>
    <div class="flash <?= $flashIsError ? 'flash-error' : 'flash-success' ?>"><?= e($flashMessage) ?></div>
  <?php endif; ?>

  <div class="discount-overview">
    <div class="discount-total-card"><span>Ukupno u evidenciji</span><strong><?= e((string) ($totals['total'] ?? 0)) ?></strong><small>kodova za popust</small></div>
    <div class="discount-total-card is-available"><span>Dostupno za izvlačenje</span><strong><?= e((string) ($totals['available'] ?? 0)) ?></strong><small>nije još dodeljeno</small></div>
    <div class="discount-total-card is-assigned"><span>Dodeljeno</span><strong><?= e((string) ($totals['assigned'] ?? 0)) ?></strong><small>vezano za Draw ID</small></div>
  </div>

  <div class="discount-stock-head"><div><h2>Zaliha po procentu</h2><p class="muted">Nagrada sa popustom koristi samo kod istog procenta.</p></div></div>
  <?php if ($stats): ?>
    <div class="discount-stock-grid">
      <?php foreach ($stats as $stat): ?>
        <div class="discount-stock-card <?= $stat['available'] <= $lowStockThreshold ? 'low-stock' : '' ?>">
          <div><span class="discount-percent"><?= e((string) $stat['percentage']) ?>%</span><span class="status-pill status-<?= $stat['available'] > 0 ? 'active' : 'failed' ?>"><?= $stat['available'] > 0 ? 'Dostupno' : 'Prazno' ?></span></div>
          <strong><?= e((string) $stat['available']) ?></strong>
          <small>spremno · <?= e((string) $stat['assigned']) ?> dodeljeno</small>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="discount-empty">Još nema unetih kodova. Uvezite kodove ispod i rezultat importa će biti prikazan ovde.</div>
  <?php endif; ?>

  <div class="discount-import-layout">
    <form method="post" action="/admin/discount-codes/import" class="form discount-import-form">
      <?= csrf_field() ?>
      <div><p class="eyebrow">Nova zaliha</p><h2>Uvezi kodove</h2><p class="muted">Jedan kod po redu. Ako unosite CSV, kod mora biti u prvoj koloni.</p></div>
      <label>Procenat popusta<input type="number" min="1" max="100" name="percentage" required placeholder="npr. 10"></label>
      <label>Kodovi<textarea name="codes" rows="10" required data-code-import placeholder="PW10-ABCD-EFGH&#10;PW10-IJKL-MNOP"></textarea><span data-code-count>0 kodova spremno za unos</span></label>
      <button class="primary" type="submit">Sačuvaj zalihu</button>
    </form>
    <aside class="discount-import-note"><strong>Važno</strong><p>Kodovi se čuvaju šifrovano. U listi se prikazuju maskirano, a puni kod se korisniku prikazuje tek kada osvoji odgovarajuću nagradu.</p><p>Posle klika na „Sačuvaj zalihu“ videćete tačan broj novih, dupliranih i nevalidnih kodova.</p></aside>
  </div>
</section>

<section class="panel">
  <div class="section-head compact-head"><div><h2>Pregled kodova</h2><p>Prikazano je najviše 500 poslednjih zapisa. Kodovi su namerno maskirani. Izvoz iznad sadrži sve kodove, grupisane po procentu.</p></div></div>
  <form method="get" action="/admin/discount-codes" class="filter-row">
    <input name="q" value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="Pretraži Draw ID ili nagradu">
    <input type="number" name="percentage" min="1" max="100" value="<?= e((string) ($filters['percentage'] ?? '')) ?>" placeholder="Procenat">
    <select name="status"><option value="">Svi statusi</option><option value="Available" <?= ($filters['status'] ?? '') === 'Available' ? 'selected' : '' ?>>Dostupni</option><option value="Assigned" <?= ($filters['status'] ?? '') === 'Assigned' ? 'selected' : '' ?>>Dodeljeni</option></select>
    <button class="ghost" type="submit">Filtriraj</button>
  </form>
  <div class="discount-table-scroll"><table class="discount-table"><thead><tr><th>Kod</th><th>Procenat</th><th>Status</th><th>Draw ID</th><th>Datum</th></tr></thead><tbody>
    <?php foreach ($codes as $code): ?><tr><td><code><?= e($code['masked_code']) ?></code></td><td><strong><?= e((string) $code['percentage']) ?>%</strong></td><td><span class="status-pill status-<?= strtolower(e($code['status'])) ?>"><?= $code['status'] === 'Available' ? 'Dostupan' : 'Dodeljen' ?></span></td><td><?= e((string) ($code['assigned_draw_id'] ?? '-')) ?></td><td><?= e(format_date($code['created_at'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$codes): ?><tr><td colspan="5">Nema zapisa za izabrani filter.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
