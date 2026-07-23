<?php if (!empty($availablePrizes)): ?>
  <section class="available-prizes" aria-labelledby="available-prizes-title">
    <div class="prize-list-head">
      <h2 id="available-prizes-title">Dostupne nagrade</h2>
      <p>Pregled nagradnog fonda, sortiran od najređih ka najčešćim nagradama.</p>
    </div>
    <ul class="prize-list">
      <?php foreach ($availablePrizes as $prize): ?>
        <?php $rarityClass = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $prize['rarity'])); ?>
        <li class="prize-item rarity-<?= e(trim($rarityClass, '-')) ?>">
          <strong><?= e($prize['name']) ?></strong>
          <span><?= e($prize['rarity']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
