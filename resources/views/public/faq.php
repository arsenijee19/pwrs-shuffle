<section class="panel readable">
  <p class="eyebrow">Pitanja i odgovori</p>
  <h1>FAQ</h1>
  <?php
  $items = [
      'Šta je PlayWorld Mystery Box?' => 'Mystery Box je digitalni proizvod iznenađenja. Nakon kupovine dobijate jedinstven link preko kog otvarate jedan box i osvajate jednu nagradu iz dostupnog nagradnog fonda.',
      'Kako funkcioniše kupovina?' => 'Nakon evidentirane uplate šaljemo Vam poseban link za svaki kupljeni Mystery Box. Otvorite link, pročitate kratke instrukcije i kliknete Open Mystery Box.',
      'Da li mogu kupiti više box-ova?' => 'Da. Možete kupiti neograničen broj Mystery Box-ova. Za svaki kupljeni box dobijate poseban link i svako otvaranje se vodi odvojeno.',
      'Da li isti link mogu otvoriti više puta?' => 'Ne. Jedan link važi za jedno otvaranje. Ako isti link otvorite ponovo nakon izvlačenja, prikazaće se već zabeležen rezultat.',
      'Da li mogu birati nagradu?' => 'Ne. Mystery Box funkcioniše po principu iznenađenja. Nagradu nije moguće unapred izabrati niti rezervisati.',
      'Da li su sve nagrade iste dostupnosti?' => 'Ne. Neke nagrade su ređe jer imaju ograničenu dostupnost ili veću vrednost. Svaki validan link prolazi kroz isti automatizovani proces izvlačenja.',
      'Koliko nagrada ima u fondu?' => 'PlayWorld garantuje da u nagradnom fondu u svakom trenutku postoji najmanje 25 aktivnih nagrada. Spisak može da se menja u skladu sa dostupnošću proizvoda i aktuelnom ponudom.',
      'Da li se glavne nagrade menjaju?' => 'Glavne nagrade ostaju deo kampanje dok su dostupne. Ako neka nagrada više nije dostupna, može biti zamenjena nagradom iste ili slične vrednosti.',
      'Šta ako već imam igru koju osvojim?' => 'Osvojenu nagradu nije moguće zameniti za drugu igru, novac, popust ili drugi proizvod. Rezultat izvlačenja je konačan nakon otvaranja.',
      'Kako znam da rezultat nije naknadno promenjen?' => 'Rezultat se upisuje odmah nakon otvaranja i vezuje se za token, vreme i Draw ID. Nakon toga isti link uvek prikazuje isti rezultat.',
      'Šta ako osvežim stranicu ili promenim uređaj?' => 'Ako je rezultat već upisan, videćete isti rezultat. Ako rezultat nije upisan, moći ćete da pokušate otvaranje ponovo.',
      'Šta ako nestane interneta tokom otvaranja?' => 'Ako je server već zabeležio rezultat, link će ga prikazati kada ponovo otvorite stranicu. Ako izvlačenje nije završeno, otvaranje se može ponoviti.',
      'Šta ako dođe do tehničkog problema?' => 'Kontaktirajte PlayWorld tim. Ako proverom utvrdimo da izvlačenje nije uspešno završeno zbog tehničke greške sistema, omogućićemo ponovno otvaranje ili izdati novi link.',
      'Koliko dugo mogu videti rezultat?' => 'Rezultat je dostupan 5 dana od trenutka otvaranja. Preporučujemo da odmah sačuvate screenshot ili preuzmete PDF potvrdu.',
      'Kako evidentiram nagradu?' => 'Pošaljite nam screenshot rezultata ili PDF potvrdu. PDF sadrži naziv nagrade, vreme izvlačenja, Draw ID i maskirani token.',
      'Šta ako izgubim link?' => 'Kontaktirajte nas što pre. Ako Mystery Box nije otvoren, pomoći ćemo Vam da ponovo pristupite linku.',
      'Kome da se javim?' => 'Javite se PlayWorld podršci ili kontakt osobi koja Vam je poslala Mystery Box link.',
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
