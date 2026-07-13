<section class="panel readable">
  <p class="eyebrow">Pitanja i odgovori</p>
  <h1>FAQ</h1>
  <?php
  $items = [
      'Šta je Mystery Box?' => 'PlayWorld Mystery Box je promotivni digitalni poklon. Svaki učesnik dobija jedinstven link preko kog može da otvori jednu Mystery Box nagradu.',
      'Kako funkcioniše?' => 'Kada otvorite link, videćete kratko objašnjenje i dostupne nagrade. Nakon potvrde, server određuje i čuva rezultat, a animacija samo prikazuje završetak procesa.',
      'Da li mogu da otvorim više puta?' => 'Ne. Jedan token važi za jedno otvaranje. Ako isti link otvorite ponovo, prikazaće se već zabeležen rezultat.',
      'Šta ako osvežim stranicu?' => 'Ako rezultat još nije zabeležen, stranica će se vratiti na prethodni korak. Ako je rezultat već zabeležen, videćete isti rezultat.',
      'Šta ako promenim uređaj?' => 'Isti link možete otvoriti i na drugom uređaju, ali rezultat ostaje isti nakon prvog uspešnog otvaranja.',
      'Da li svi učesnici prolaze kroz isti proces?' => 'Da. Svaki validan token prolazi kroz isti serverski proces. Nagrade mogu imati različitu dostupnost, ali rezultat se ne bira na uređaju korisnika.',
      'Zašto postoje ređe nagrade?' => 'Neke nagrade su količinski ograničene ili imaju veću vrednost, pa su prirodno manje dostupne u okviru promocije.',
      'Kako znam da rezultat nije naknadno promenjen?' => 'Rezultat se upisuje odmah nakon otvaranja i vezuje se za token, vreme i Draw ID. Nakon toga korisniku se uvek prikazuje isti rezultat.',
      'Šta ako nestane interneta?' => 'Ako je rezultat već upisan, link će ga prikazati kada ponovo otvorite stranicu. Ako nije upisan, moći ćete da pokušate ponovo.',
      'Koliko dugo mogu videti rezultat?' => 'Rezultat je dostupan 5 dana od trenutka otvaranja. Preporučujemo da preuzmete PDF potvrdu.',
      'Kako preuzimam nagradu?' => 'Pratite uputstvo prikazano uz osvojenu nagradu. Ako nešto nije jasno, javite se PlayWorld timu sa PDF potvrdom.',
      'Čemu služi PDF?' => 'PDF je potvrda rezultata za vašu evidenciju. Sadrži naziv nagrade, vreme, Draw ID, maskirani token i QR kod ka rezultatu.',
      'Kome da se javim?' => 'Javite se PlayWorld podršci ili kontakt osobi koja vam je poslala Mystery Box link.',
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
