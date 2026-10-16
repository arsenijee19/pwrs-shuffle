<?php
$tabs = ['' => ['Svi', $total], 'New' => ['Novi', $counts['New'] ?? 0], 'InProgress' => ['U toku', $counts['InProgress'] ?? 0], 'Delivered' => ['Isporučeni', $counts['Delivered'] ?? 0], 'Cancelled' => ['Otkazani', $counts['Cancelled'] ?? 0]];
?>
<section class="panel">
  <div class="section-head">
    <div><p class="eyebrow">Ručna isporuka</p><h1>Zahtevi za nagrade</h1></div>
  </div>
  <div class="filter-tabs" role="tablist" aria-label="Filter po statusu">
    <?php foreach ($tabs as $key => [$label, $count]): ?>
      <a href="/admin/reward-claims<?= $key !== '' ? '?status=' . e($key) : '' ?>" class="<?= $filter === $key ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $filter === $key ? 'true' : 'false' ?>"><?= e($label) ?> <span><?= (int) $count ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php if ($claims): ?>
    <table>
      <thead><tr><th>Primljeno</th><th>Nagrada</th><th>Telefon</th><th>Email</th><th>Status</th><th>Draw ID</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($claims as $claim): $st = claim_status((string) $claim['status']); ?>
          <tr>
            <td><?= e(format_date((string) $claim['created_at'])) ?></td>
            <td><strong><?= e($claim['prize_name']) ?></strong></td>
            <td><a class="text-link" href="tel:+<?= e(phone_international((string) $claim['recipient_phone'])) ?>"><?= e($claim['recipient_phone']) ?></a></td>
            <td><?= e($claim['recipient_email']) ?></td>
            <td><span class="chip <?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
            <td><code><?= e($claim['draw_id']) ?></code></td>
            <td class="row-actions"><a class="primary" href="/admin/reward-claims/<?= e((string) $claim['id']) ?>">Otvori</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="empty-state">Nema zahteva u ovom prikazu.</p>
  <?php endif; ?>
</section>
