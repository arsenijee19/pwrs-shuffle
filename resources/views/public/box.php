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
      <h2 id="available-prizes-title">Dostupne nagrade</h2>
      <div class="prize-list">
        <?php foreach ($availablePrizes as $prize): ?>
          <article class="prize-item">
            <img src="<?= e($prize['cover_path']) ?>" alt="">
            <div>
              <strong><?= e($prize['name']) ?></strong>
              <span><?= e($prize['rarity']) ?></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
  <a class="text-link" href="/faq">Pročitaj više</a>
  <form method="post" action="/box/<?= e($token) ?>/open" data-confirm-open>
    <?= csrf_field() ?>
    <p class="notice">Ovu Mystery Box nagradu možete otvoriti samo jednom.</p>
    <button class="primary huge" type="submit">Open Mystery Box</button>
  </form>
</section>
