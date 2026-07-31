<section class="admin-grid">
  <div class="stat"><span>Email pokušaji</span><strong><?= e((string) $stats['total']) ?></strong></div>
  <div class="stat"><span>Poslato</span><strong><?= e((string) $stats['sent']) ?></strong></div>
  <div class="stat"><span>Greške</span><strong><?= e((string) $stats['failed']) ?></strong></div>
</section>
<section class="panel">
  <div class="section-head">
    <div>
      <p class="eyebrow">Email isporuka</p>
      <h1>Istorija slanja</h1>
      <p class="lead">Pregled svakog pokušaja slanja Mystery Box linka iz admin panela.</p>
    </div>
    <div class="actions">
      <a class="ghost" href="/admin/email">Email podešavanja</a>
      <a class="primary" href="/admin/tokens">Tokeni</a>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Vreme</th>
        <th>Email</th>
        <th>Ime</th>
        <th>Token</th>
        <th>Status slanja</th>
        <th>Status tokena</th>
        <th>Greška</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($deliveries as $delivery): ?>
        <tr>
          <td><?= e(format_date($delivery['created_at'])) ?></td>
          <td><?= e($delivery['recipient_email']) ?></td>
          <td><?= e($delivery['recipient_name'] !== '' ? $delivery['recipient_name'] : '-') ?></td>
          <td><?= e($delivery['token_id'] ? '#' . (string) $delivery['token_id'] : '-') ?></td>
          <td><span class="status-pill status-<?= e(strtolower($delivery['status'])) ?>"><?= e($delivery['status']) ?></span></td>
          <td><?= e($delivery['token_status'] ?? '-') ?></td>
          <td class="table-note"><?= e($delivery['error_message'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($deliveries)): ?>
        <tr><td colspan="7">Još nema poslatih emailova.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>
