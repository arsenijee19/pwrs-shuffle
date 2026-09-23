<section class="panel"><div class="section-head"><div><p class="eyebrow">Ručna isporuka</p><h1>Zahtevi za nagrade</h1></div><a class="ghost" href="/admin/prizes">Nazad na nagrade</a></div>
  <table><thead><tr><th>Draw ID</th><th>Nagrada</th><th>Email</th><th>Telefon</th><th>Status</th><th>Telegram</th><th></th></tr></thead><tbody>
  <?php foreach ($claims as $claim): ?><tr><td><code><?= e($claim['draw_id']) ?></code></td><td><?= e($claim['prize_name']) ?></td><td><?= e($claim['recipient_email']) ?></td><td><?= e($claim['recipient_phone']) ?></td><td><?= e($claim['status']) ?></td><td><?= e($claim['notification_status']) ?></td><td><a class="ghost" href="/admin/reward-claims/<?= e((string) $claim['id']) ?>">Otvori</a></td></tr><?php endforeach; ?>
  </tbody></table>
  <?php if (!$claims): ?><p class="muted">Još nema zahteva.</p><?php endif; ?>
</section>
