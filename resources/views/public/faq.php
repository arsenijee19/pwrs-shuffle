<section class="panel readable">
  <p class="eyebrow">Pitanja i odgovori</p>
  <h1>FAQ</h1>
  <?php
  $items = [
      'Šta je Mystery Box?' => 'PlayWorld promotivno izvlačenje u kome jedan jedinstveni link vodi do jedne unapred definisane nagrade.',
      'Kako funkcioniše?' => 'Otvorite link, potvrdite otvaranje i server upisuje rezultat pre animacije.',
      'Da li može više puta?' => 'Ne. Svaki token može da se iskoristi samo jednom.',
      'Šta ako osvežim stranicu?' => 'Ako je rezultat već upisan, videćete isti rezultat.',
      'Šta ako promenim uređaj?' => 'Isti link prikazuje isti rezultat dok je rezultat dostupan.',
      'Da li svi imaju iste šanse?' => 'Svi tokeni prolaze kroz isti serverski proces, ali nagrade mogu imati različitu retkost.',
      'Zašto postoje retke nagrade?' => 'Promocija može imati ograničen broj vrednijih ili ređih nagrada.',
      'Kako znati da nije namešteno?' => 'Rezultat se čuva odmah, administrator ga ne može promeniti nakon osvajanja, a svako izvlačenje ima Draw ID.',
      'Šta ako nestane interneta?' => 'Ako je server već upisao rezultat, link će kasnije prikazati isti rezultat.',
      'Koliko dugo mogu videti rezultat?' => 'Rezultat je dostupan 5 dana od otvaranja.',
      'Kako preuzimam nagradu?' => 'Pratite uputstvo prikazano uz osvojenu nagradu ili kontaktirajte PlayWorld tim.',
      'Čemu služi PDF?' => 'PDF je potvrda rezultata za vašu evidenciju.',
      'Kome da se javim?' => 'Javite se PlayWorld podršci ili kontakt osobi koja vam je poslala link.',
  ];
  ?>
  <div class="faq">
    <?php foreach ($items as $q => $a): ?>
      <details>
        <summary><?= e($q) ?></summary>
        <p><?= e($a) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>
