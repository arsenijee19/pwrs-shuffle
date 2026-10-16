<section class="panel">
  <div class="section-head">
    <div>
      <p class="eyebrow">n8n / order fulfillment</p>
      <h1>Automatske isporuke</h1>
      <p class="lead">Pregled svakog order-a i statusa pojedinačnih Mystery Box linkova.</p>
    </div>
    <div class="actions">
    </div>
  </div>
  <?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?><div class="flash flash-<?= e((string) ($flash['type'] ?? 'success')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></div><?php endif; ?>
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
        <th>Akcija</th>
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
                <?php $itemStatus = ($item['token_status'] ?? '') === 'Revoked' ? 'Revoked' : (string) $item['status']; ?>
                <span class="status-pill status-<?= e(strtolower($itemStatus)) ?>">#<?= e((string) $item['sequence_number']) ?> <?= e($itemStatus) ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td><?= e(format_date($fulfillment['created_at'])) ?></td>
          <td>
            <?php if ($fulfillment['status'] === 'Cancelled'): ?>
              <span class="muted">Poništeno</span>
            <?php else: ?>
              <form method="post" action="/admin/fulfillments/<?= e((string) $fulfillment['id']) ?>/cancel" class="inline" data-confirm-action="Poništiti sve još aktivne linkove iz ovog n8n paketa?">
                <?= csrf_field() ?>
                <button class="danger" type="submit">Poništi</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($fulfillments)): ?>
        <tr><td colspan="8">Još nema API isporuka.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>
