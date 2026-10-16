<section class="panel narrow open-panel">
  <div class="gift-hero" aria-hidden="true">
    <div class="gift-glow"></div>
    <div class="gift">
      <div class="gift-body"></div>
      <div class="gift-lid"></div>
      <div class="gift-bow"><i></i><i></i><b></b></div>
    </div>
    <span class="gift-spark s1"></span><span class="gift-spark s2"></span><span class="gift-spark s3"></span>
  </div>
  <p class="eyebrow">Vaš link je spreman</p>
  <h1>Vaš Mystery Box je spreman</h1>
  <p class="lead">Otvorite box kada ste spremni. Jedan link važi za jedno otvaranje, a rezultat se odmah čuva u PlayWorld evidenciji.</p>
  <form class="open-cta" method="post" action="/box/<?= e($token) ?>/open" data-confirm-open>
    <?= csrf_field() ?>
    <p class="notice">Ovaj link možete otvoriti samo jednom.</p>
    <button class="primary huge" type="submit">Otvorite Mystery Box</button>
  </form>
</section>

<section class="panel narrow open-details-panel">
  <div class="open-details">
    <div class="compact-head">
      <h2>Šta je važno da znate?</h2>
      <p>Kratak pregled pravila pre otvaranja.</p>
    </div>
    <ul class="checklist">
      <li>Svaki Mystery Box može da se otvori samo jednom.</li>
      <li>U fondu je najmanje 25 aktivnih nagrada.</li>
      <li>Nagrade imaju različite vrednosti, pa se vrednije nagrade pojavljuju ređe.</li>
      <li>Rezultat određuje server, a ne animacija.</li>
      <li>Nakon otvaranja rezultat nije moguće promeniti.</li>
      <li>Rezultat će biti dostupan narednih 5 dana.</li>
      <li>Po želji možete preuzeti PDF potvrdu.</li>
      <li>Osvojeni popusti mogu se iskoristiti na sajtu playworld.rs za sve igre i pretplate iz ponude.</li>
      <li>Popusti nemaju rok trajanja.</li>
      <li>Ako već imate osvojenu igru, novi box je moguć samo uz proveru PlayWorld kupovine te igre.</li>
    </ul>
  </div>
  <a class="text-link" href="/faq">Pročitajte više</a>
</section>

<?php require app_path('resources/views/partials/prize-list.php'); ?>
