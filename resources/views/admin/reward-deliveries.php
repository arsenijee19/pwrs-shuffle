<section class="panel"><div class="section-head"><div><p class="eyebrow">Automatska isporuka</p><h1>Bonus Mystery Box isporuke</h1></div><a class="ghost" href="/admin/prizes">Nazad na nagrade</a></div>
  <table><thead><tr><th>Draw ID</th><th>Nagrada</th><th>Email</th><th>Broj linkova</th><th>Status</th><th>Kreirano</th></tr></thead><tbody>
  <?php foreach ($deliveries as $delivery): ?><tr><td><code><?= e($delivery['draw_id']) ?></code></td><td><?= e($delivery['prize_name']) ?></td><td><?= e($delivery['recipient_email']) ?></td><td><?= e((string) $delivery['requested_quantity']) ?></td><td><span class="status-pill"><?= e($delivery['status']) ?></span></td><td><?= e(format_date($delivery['created_at'])) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <?php if (!$deliveries): ?><p class="muted">Još nema bonus isporuka.</p><?php endif; ?>
</section>
