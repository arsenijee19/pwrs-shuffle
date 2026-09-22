<section class="panel narrow open-panel">
  <p class="eyebrow">Vaš link je spreman</p>
  <h1>Vaš Mystery Box je spreman</h1>
  <p class="lead">Otvorite box kada ste spremni. Jedan link važi za jedno otvaranje, a rezultat se odmah čuva u PlayWorld evidenciji.</p>
  <form class="open-cta" method="post" action="/box/<?= e($token) ?>/open" data-confirm-open>
    <?= csrf_field() ?>
    <p class="notice">Ovu Mystery Box nagradu možete otvoriti samo jednom.</p>
    <button class="primary huge" type="submit">Open Mystery Box</button>
  </form>
</section>

<section class="panel narrow open-details-panel">
  <div class="open-details">
    <div class="compact-head">
      <h2>Šta treba da znate?</h2>
      <p>Kratak pregled pravila pre otvaranja.</p>
    </div>
    <ul class="checklist">
      <li>Svaki Mystery Box može da se otvori samo jednom.</li>
      <li>U fondu je najmanje 25 aktivnih nagrada.</li>
      <li>Neke nagrade imaju ograničenu dostupnost.</li>
      <li>Rezultat određuje server.</li>
      <li>Nakon otvaranja rezultat nije moguće promeniti.</li>
      <li>Rezultat će biti dostupan narednih 5 dana.</li>
      <li>Po želji možete preuzeti PDF potvrdu.</li>
      <li>Popust od 5% može se sabirati do ukupno 30%; popusti veći od 20% se ne kombinuju.</li>
      <li>Ako već imate osvojenu igru, novi box je moguć samo uz proveru PlayWorld kupovine te igre.</li>
    </ul>
  </div>
  <a class="text-link" href="/faq">Pročitaj više</a>
</section>

<?php require app_path('resources/views/partials/prize-list.php'); ?>
