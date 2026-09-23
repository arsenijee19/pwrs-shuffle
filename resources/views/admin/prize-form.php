<section class="panel narrow">
  <h1><?= $prize ? 'Izmena nagrade' : 'Nova nagrada' ?></h1>
  <form method="post" action="/admin/prizes/save" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <?php if ($prize): ?>
      <input type="hidden" name="id" value="<?= e((string) $prize['id']) ?>">
      <input type="hidden" name="existing_cover" value="<?= e($prize['cover_path']) ?>">
    <?php endif; ?>
    <label>Naziv<input name="name" required value="<?= e($prize['name'] ?? '') ?>"></label>
    <label>Opis<textarea name="description" required><?= e($prize['description'] ?? '') ?></textarea></label>
    <label>Uputstvo<textarea name="instructions"><?= e($prize['instructions'] ?? '') ?></textarea></label>
    <label>Cover<input type="file" name="cover" accept="image/png,image/jpeg,image/webp"></label>
    <label>Rarity<input name="rarity" required value="<?= e($prize['rarity'] ?? 'Standard') ?>"></label>
    <label>Weight<input type="number" name="weight" min="0" required value="<?= e((string) ($prize['weight'] ?? 10)) ?>"></label>
    <label>Maksimalan broj<input type="number" name="max_quantity" min="1" required value="<?= e((string) ($prize['max_quantity'] ?? 1)) ?>"></label>
    <fieldset class="reward-behavior">
      <legend>Automatizacija nagrade</legend>
      <label>Broj dodatnih Mystery Box-ova<input type="number" name="bonus_box_count" min="0" max="50" value="<?= e((string) ($prize['bonus_box_count'] ?? 0)) ?>"></label>
      <label class="checkbox"><input type="checkbox" name="discount_enabled" <?= !empty($prize['discount_percent']) ? 'checked' : '' ?>> Sadrži kod za popust</label>
      <label>Procenat popusta<input type="number" name="discount_percent" min="1" max="100" value="<?= e((string) ($prize['discount_percent'] ?? '')) ?>"></label>
      <label class="checkbox"><input type="checkbox" name="manual_claim" <?= !empty($prize['manual_claim']) ? 'checked' : '' ?>> Ručna isporuka preko PlayWorld tima</label>
      <p class="muted">Ove vrednosti se čuvaju odvojeno od naziva nagrade i koriste se na serveru.</p>
    </fieldset>
    <label class="checkbox"><input type="checkbox" name="active" <?= !$prize || (int) $prize['active'] === 1 ? 'checked' : '' ?>> Aktivna</label>
    <button class="primary">Sačuvaj</button>
  </form>
</section>
