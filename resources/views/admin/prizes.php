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
    </div>
  </div>
  <?php if (!empty($_SESSION['flash'])): ?>
    <?php
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $isError = is_array($flash) && ($flash['type'] ?? '') === 'error';
    $message = is_array($flash) && isset($flash['message'])
        ? (string) $flash['message']
        : 'Katalog je dodat. Novo: ' . (int) ($flash['created'] ?? 0) . ', osveženo: ' . (int) ($flash['updated'] ?? 0) . '.';
    ?>
    <p class="<?= $isError ? 'alert' : 'notice' ?>" role="<?= $isError ? 'alert' : 'status' ?>"><?= e($message) ?></p>
  <?php endif; ?>
  <table>
    <thead><tr><th>Cover</th><th>Naziv</th><th>Rarity</th><th>Weight</th><th>Legacy max</th><th>Automatika</th><th>Osvojeno</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($prizes as $prize): ?>
        <tr>
          <td><img class="thumb" src="<?= e($prize['cover_path']) ?>" alt=""></td>
          <td><?= e($prize['name']) ?></td>
          <td><select class="inline-editor" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="rarity" data-current-value="<?= e($prize['rarity']) ?>"><option value="Ultra Rare" <?= $prize['rarity'] === 'Ultra Rare' ? 'selected' : '' ?>>Ultra Rare</option><option value="Rare" <?= $prize['rarity'] === 'Rare' ? 'selected' : '' ?>>Rare</option><option value="Special" <?= $prize['rarity'] === 'Special' ? 'selected' : '' ?>>Special</option><option value="Standard" <?= $prize['rarity'] === 'Standard' ? 'selected' : '' ?>>Standard</option><option value="Common" <?= $prize['rarity'] === 'Common' ? 'selected' : '' ?>>Common</option></select><span class="inline-save-state" data-inline-state></span></td>
          <td><input class="inline-editor inline-number" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="weight" data-current-value="<?= e((string) $prize['weight']) ?>" type="number" min="0" value="<?= e((string) $prize['weight']) ?>"><span class="inline-save-state" data-inline-state></span></td>
          <td title="Ne utiče na izvlačenje"><input class="inline-editor inline-number legacy-field" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="max_quantity" data-current-value="<?= e((string) $prize['max_quantity']) ?>" type="number" min="1" value="<?= e((string) $prize['max_quantity']) ?>"><span class="inline-save-state" data-inline-state></span><small class="legacy-note">informativno</small></td>
          <td class="prize-automation">
            <label><span>Box</span><input class="inline-editor inline-number" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="bonus_box_count" data-current-value="<?= e((string) ($prize['bonus_box_count'] ?? 0)) ?>" type="number" min="0" max="50" value="<?= e((string) ($prize['bonus_box_count'] ?? 0)) ?>"><small class="inline-save-state" data-inline-state></small></label>
            <label><span>Popust %</span><input class="inline-editor inline-number" data-prize-id="<?= e((string) $prize['id']) ?>" data-field="discount_percent" data-current-value="<?= e((string) ($prize['discount_percent'] ?? '')) ?>" type="number" min="1" max="100" value="<?= e((string) ($prize['discount_percent'] ?? '')) ?>"><small class="inline-save-state" data-inline-state></small></label>
            <span class="automation-note"><?= !empty($prize['manual_claim']) ? 'Preuzimanje: ručno' : 'Isporuka: automatska' ?></span>
          </td>
          <td data-prize-won><?= e((string) $prize['won_count']) ?></td>
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
              <form method="post" action="/admin/prizes/<?= e((string) $prize['id']) ?>/delete" class="inline" data-confirm-action="Obrisati nagradu „<?= e($prize['name']) ?>”? Ova radnja se ne može poništiti."><?= csrf_field() ?><button class="danger" type="submit">Obriši</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
