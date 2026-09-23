<section class="panel">
  <div class="section-head admin-page-head">
    <div>
      <p class="eyebrow">Nagradni fond</p>
      <h1>Nagrade</h1>
    </div>
    <div class="actions admin-actions">
      <form method="post" action="/admin/prizes/seed-playworld" class="inline">
        <?= csrf_field() ?>
        <button class="ghost" type="submit">Dodaj PlayWorld katalog</button>
      </form>
      <form method="post" action="/admin/prizes/seed-bonus" class="inline">
        <?= csrf_field() ?>
        <button class="ghost" type="submit">Dodaj bonus nagrade</button>
      </form>
      <a class="primary" href="/admin/prizes/new">Dodaj nagradu</a>
      <a class="ghost" href="/admin/discount-codes">Kodovi za popust</a>
      <a class="ghost" href="/admin/reward-deliveries">Bonus isporuke</a>
      <a class="ghost" href="/admin/reward-claims">Zahtevi</a>
    </div>
  </div>
  <?php if (!empty($_SESSION['flash'])): ?>
    <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <p class="notice <?= is_array($flash) && ($flash['type'] ?? '') === 'error' ? 'alert' : '' ?>">
      <?= is_array($flash) ? e((string) ($flash['message'] ?? '')) : 'Katalog je dodat. Novo: ' . e((string) ($flash['created'] ?? 0)) . ', osveženo: ' . e((string) ($flash['updated'] ?? 0)) . '.' ?>
    </p>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
  <table>
    <thead><tr><th>Cover</th><th>Naziv</th><th>Rarity</th><th>Weight</th><th>Max</th><th>Osvojeno</th><th>Preostalo</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($prizes as $prize): ?>
        <tr>
          <td><img class="thumb" src="<?= e($prize['cover_path']) ?>" alt=""></td>
          <td><?= e($prize['name']) ?></td>
          <td><select class="inline-editor" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="rarity" data-current-value="<?= e($prize['rarity']) ?>"><option value="Ultra Rare" <?= $prize['rarity'] === 'Ultra Rare' ? 'selected' : '' ?>>Ultra Rare</option><option value="Rare" <?= $prize['rarity'] === 'Rare' ? 'selected' : '' ?>>Rare</option><option value="Special" <?= $prize['rarity'] === 'Special' ? 'selected' : '' ?>>Special</option><option value="Standard" <?= $prize['rarity'] === 'Standard' ? 'selected' : '' ?>>Standard</option><option value="Common" <?= $prize['rarity'] === 'Common' ? 'selected' : '' ?>>Common</option></select><span class="inline-save-state" data-inline-state></span></td>
          <td><input class="inline-editor inline-number" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="weight" data-current-value="<?= e((string) $prize['weight']) ?>" type="number" min="0" value="<?= e((string) $prize['weight']) ?>"><span class="inline-save-state" data-inline-state></span></td>
          <td><input class="inline-editor inline-number" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="max_quantity" data-current-value="<?= e((string) $prize['max_quantity']) ?>" type="number" min="<?= e((string) max(1, (int) $prize['won_count'])) ?>" value="<?= e((string) $prize['max_quantity']) ?>"><span class="inline-save-state" data-inline-state></span></td>
          <td data-prize-won><?= e((string) $prize['won_count']) ?></td>
          <td data-prize-remaining><?= e((string) max(0, (int) $prize['max_quantity'] - (int) $prize['won_count'])) ?></td>
          <td>
            <form method="post" action="/admin/prizes/<?= e((string) $prize['id']) ?>/toggle" class="inline">
              <?= csrf_field() ?>
              <button class="status-toggle <?= ((int) $prize['active']) === 1 ? 'is-on' : 'is-off' ?>" type="submit" role="switch" aria-checked="<?= ((int) $prize['active']) === 1 ? 'true' : 'false' ?>">
                <span class="toggle-track" aria-hidden="true"><span class="toggle-thumb"></span></span>
                <span><?= ((int) $prize['active']) === 1 ? 'On' : 'Off' ?></span>
              </button>
            </form>
          </td>
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
