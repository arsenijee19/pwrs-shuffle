<section class="panel">
  <div class="section-head">
    <div>
      <p class="eyebrow">n8n / order fulfillment</p>
      <h1>Automatske isporuke</h1>
      <p class="lead">Pregled svakog order-a i statusa pojedinačnih Mystery Box linkova.</p>
    </div>
    <div class="actions">
      <a class="ghost" href="/admin/tokens">Tokeni</a>
      <a class="primary" href="/admin">Dashboard</a>
    </div>
  </div>
  <div class="notice notice-info">
    <strong>NeedsReview</strong> znači da je SMTP možda prihvatio poruku, ali aplikacija nije mogla pouzdano da potvrdi završni status. Takav item se ne šalje ponovo automatski.
  </div>
  <table>
    <thead>
      <tr>
        <th>Order ID</th>
        <th>Email</th>
        <th>Quantity</th>
        <th>Poslato</th>
        <th>Status</th>
        <th>Item-i</th>
        <th>Vreme</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($fulfillments as $fulfillment): ?>
        <tr>
          <td><strong><?= e($fulfillment['order_id']) ?></strong></td>
          <td><?= e($fulfillment['recipient_email']) ?></td>
          <td><?= e((string) $fulfillment['requested_quantity']) ?></td>
          <td><?= e((string) $fulfillment['sent_quantity']) ?></td>
          <td><span class="status-pill status-<?= e(strtolower((string) $fulfillment['status'])) ?>"><?= e($fulfillment['status']) ?></span></td>
          <td>
            <div class="item-statuses">
              <?php foreach ($fulfillment['items'] as $item): ?>
                <span class="status-pill status-<?= e(strtolower((string) $item['status'])) ?>">#<?= e((string) $item['sequence_number']) ?> <?= e($item['status']) ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td><?= e(format_date($fulfillment['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($fulfillments)): ?>
        <tr><td colspan="7">Još nema API isporuka.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>
