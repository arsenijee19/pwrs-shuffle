<section class="panel narrow">
  <p class="eyebrow">Vaš link je spreman</p>
  <h1>Kako funkcioniše?</h1>
  <ul class="checklist">
    <li>Svaki Mystery Box može da se otvori samo jednom.</li>
    <li>Nagradni fond je pripremljen pre početka promocije.</li>
    <li>Neke nagrade imaju ograničenu dostupnost.</li>
    <li>Rezultat određuje server.</li>
    <li>Nakon otvaranja rezultat nije moguće promeniti.</li>
    <li>Rezultat će biti dostupan narednih 5 dana.</li>
    <li>Po želji možete preuzeti PDF potvrdu.</li>
  </ul>
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
  <a class="text-link" href="/faq">Pročitaj više</a>
  <form method="post" action="/box/<?= e($token) ?>/open" data-confirm-open>
    <?= csrf_field() ?>
    <p class="notice">Ovu Mystery Box nagradu možete otvoriti samo jednom.</p>
    <button class="primary huge" type="submit">Open Mystery Box</button>
  </form>
</section>
