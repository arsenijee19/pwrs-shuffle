<section class="panel narrow">
  <p class="eyebrow">Vaš link je spreman</p>
  <h1>Kako funkcioniše?</h1>
  <ul class="checklist">
    <li>Svaki Mystery Box može da se otvori samo jednom.</li>
    <li>Sve nagrade su unapred definisane.</li>
    <li>Neke nagrade su znatno ređe od drugih.</li>
    <li>Rezultat određuje server.</li>
    <li>Nakon otvaranja rezultat nije moguće promeniti.</li>
    <li>Rezultat će biti dostupan narednih 5 dana.</li>
    <li>Po želji možete preuzeti PDF potvrdu.</li>
  </ul>
  <a class="text-link" href="/faq">Pročitaj više</a>
  <form method="post" action="/box/<?= e($token) ?>/open" data-confirm-open>
    <?= csrf_field() ?>
    <p class="notice">Ovu Mystery Box nagradu možete otvoriti samo jednom.</p>
    <button class="primary huge" type="submit">Open Mystery Box</button>
  </form>
</section>
