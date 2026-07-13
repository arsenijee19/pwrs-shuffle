<section class="panel">
  <div class="section-head">
    <h1>Nagrade</h1>
    <div class="actions">
      <form method="post" action="/admin/prizes/seed-playworld" class="inline">
        <?= csrf_field() ?>
        <button class="ghost" type="submit">Dodaj PlayWorld katalog</button>
      </form>
      <a class="primary" href="/admin/prizes/new">Dodaj nagradu</a>
    </div>
  </div>
  <?php if (!empty($_SESSION['flash'])): ?>
    <p class="notice">Katalog je dodat. Novo: <?= e((string) $_SESSION['flash']['created']) ?>, osveženo: <?= e((string) $_SESSION['flash']['updated']) ?>.</p>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
  <table>
    <thead><tr><th>Cover</th><th>Naziv</th><th>Rarity</th><th>Weight</th><th>Max</th><th>Osvojeno</th><th>Preostalo</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($prizes as $prize): ?>
        <tr>
          <td><img class="thumb" src="<?= e($prize['cover_path']) ?>" alt=""></td>
          <td><?= e($prize['name']) ?></td>
          <td><?= e($prize['rarity']) ?></td>
          <td><?= e((string) $prize['weight']) ?></td>
          <td><?= e((string) $prize['max_quantity']) ?></td>
          <td><?= e((string) $prize['won_count']) ?></td>
          <td><?= e((string) max(0, (int) $prize['max_quantity'] - (int) $prize['won_count'])) ?></td>
          <td><?= ((int) $prize['active']) === 1 ? 'Aktivna' : 'Neaktivna' ?></td>
          <td class="row-actions">
            <a class="ghost" href="/admin/prizes/<?= e((string) $prize['id']) ?>/edit">Izmeni</a>
            <?php if ((int) $prize['won_count'] === 0): ?>
              <form method="post" action="/admin/prizes/<?= e((string) $prize['id']) ?>/delete" class="inline"><?= csrf_field() ?><button class="danger">Obriši</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
