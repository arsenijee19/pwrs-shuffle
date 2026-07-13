<section class="admin-grid">
  <div class="stat"><span>Tokeni</span><strong><?= e((string) $stats['total']) ?></strong></div>
  <div class="stat"><span>Iskorišćeni</span><strong><?= e((string) $stats['used']) ?></strong></div>
  <div class="stat"><span>Neiskorišćeni</span><strong><?= e((string) $stats['unused']) ?></strong></div>
</section>
<section class="panel">
  <div class="section-head">
    <h1>Dashboard</h1>
    <div class="actions">
      <a class="primary" href="/admin/tokens">Tokeni</a>
      <a class="ghost" href="/admin/prizes">Nagrade</a>
      <a class="ghost" href="/admin/password">Lozinka</a>
    </div>
  </div>
  <h2>Poslednja otvaranja</h2>
  <table>
    <thead><tr><th>Draw ID</th><th>Nagrada</th><th>Vreme</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $draw): ?>
        <tr><td><?= e($draw['draw_id']) ?></td><td><?= e($draw['prize_name']) ?></td><td><?= e(format_date($draw['created_at'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <h2>Broj osvojenih nagrada</h2>
  <table>
    <thead><tr><th>Nagrada</th><th>Osvojeno</th></tr></thead>
    <tbody>
      <?php foreach ($counts as $row): ?>
        <tr><td><?= e($row['name']) ?></td><td><?= e((string) $row['won_count']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
