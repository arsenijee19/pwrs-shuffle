<section class="panel readable faq-panel">
  <p class="eyebrow">Pitanja i odgovori</p>
  <h1>FAQ</h1>
  <p class="lead">Kratko i jasno objašnjenje kako PlayWorld Mystery Box funkcioniše, šta možete da očekujete i šta se dešava nakon otvaranja linka.</p>
  <?php
  $sections = [
      'Kupovina i link' => [
          'Šta je PlayWorld Mystery Box?' => 'PlayWorld Mystery Box je digitalni box iznenađenja. Nakon kupovine dobijate jedinstven link, otvarate box i sistem Vam prikazuje jednu osvojenu nagradu iz aktivnog nagradnog fonda.',
          'Kako funkcioniše kupovina?' => 'Kada uplata bude evidentirana, PlayWorld Vam šalje poseban link za svaki kupljeni Mystery Box. Na linku vidite kratke instrukcije, pregled dostupnih nagrada i dugme za otvaranje.',
          'Da li mogu kupiti više box-ova?' => 'Da. Možete kupiti neograničen broj Mystery Box-ova. Svaki kupljeni box dobija svoj poseban link, pa se svako otvaranje vodi odvojeno kao nova prilika za nagradu.',
          'Da li isti link mogu otvoriti više puta?' => 'Jedan link može da se iskoristi samo jednom. Kada je rezultat jednom zabeležen, isti link kasnije služi samo za pregled već osvojene nagrade, dok je rezultat dostupan.',
      ],
      'Izvlačenje i nagrade' => [
          'Da li mogu birati nagradu?' => 'Ne. Mystery Box je proizvod iznenađenja i nagradu nije moguće unapred izabrati, rezervisati ili zameniti pre otvaranja.',
          'Kako se određuje rezultat?' => 'Rezultat određuje server u trenutku otvaranja. Prvo se zabeleži rezultat, a tek nakon toga se prikazuje animacija. Animacija je samo vizuelni prikaz i ne odlučuje nagradu.',
          'Da li su sve nagrade iste dostupnosti?' => 'Ne. Neke nagrade se pojavljuju ređe jer imaju veću vrednost ili ograničenu dostupnost. Svaki validan link prolazi kroz isti automatizovani proces izvlačenja.',
          'Koliko nagrada ima u fondu?' => 'PlayWorld garantuje da se u nagradnom fondu u svakom trenutku nalazi najmanje 25 aktivnih nagrada. Spisak može da se menja u skladu sa dostupnošću proizvoda i aktuelnom ponudom.',
          'Da li se glavne nagrade menjaju?' => 'Glavne nagrade ostaju deo promocije dok su dostupne. Ako neka nagrada više nije dostupna, može biti zamenjena nagradom iste ili slične vrednosti.',
          'Kako funkcionišu popusti i bonus nagrade?' => 'Popust od 5% može se ostvariti više puta i sabirati do maksimalnih 30%. Popusti veći od 20% ne mogu se kombinovati sa drugim popustima u jednoj kupovini. Ostale bonus nagrade važe prema uslovima navedenim uz konkretnu nagradu.',
          'Da li zaštita od ponavljanja utiče na izvlačenje?' => 'Zaštita sprečava samo da se ista nagrada istom anonimizovanom mrežnom identifikatoru pojavi dva puta uzastopno. Nakon što se izvuče druga nagrada, prethodna se odmah vraća u pool po svojoj punoj težini. Ovo nije lista ranije osvojenih nagrada, ne garantuje različite nagrade u većoj kupovini i ne menja podešene težine. Ako nema druge validne nagrade, izvlačenje se normalno nastavlja.',
          'Šta ako već imam igru koju osvojim?' => 'Nema zamene za novac, drugi proizvod ili refundacije nakon otvaranja. Ako već imate osvojenu igru, novi Mystery Box može biti odobren samo ako PlayWorld evidencija potvrdi da je ta ista igra prethodno kupljena preko PlayWorld-a. U svim ostalim slučajevima rezultat ostaje važeći.',
      ],
      'Sigurnost, potvrda i pomoć' => [
          'Kako znam da rezultat nije naknadno promenjen?' => 'Kada otvorite box, rezultat se vezuje za token, vreme otvaranja i jedinstveni Draw ID. Nakon toga administrator ne može da promeni već osvojenu nagradu, a isti link uvek prikazuje isti rezultat.',
          'Šta ako osvežim stranicu ili promenim uređaj?' => 'Ako je rezultat već upisan, ponovo ćete videti isti rezultat. Ako izvlačenje nije završeno, link ostaje spreman za otvaranje.',
          'Šta ako nestane interneta tokom otvaranja?' => 'Ako je server već zabeležio rezultat, videćete ga kada ponovo otvorite link. Ako rezultat nije zabeležen, otvaranje se može ponoviti.',
          'Šta ako dođe do tehničkog problema?' => 'Kontaktirajte PlayWorld tim. Ako proverom utvrdimo da izvlačenje nije uspešno završeno zbog tehničke greške sistema, omogućićemo ponovno otvaranje ili izdati novi Mystery Box link.',
          'Koliko dugo mogu videti rezultat?' => 'Rezultat je dostupan 5 dana od trenutka otvaranja. Preporučujemo da odmah sačuvate screenshot ili preuzmete PDF potvrdu.',
          'Kako evidentiram nagradu?' => 'Pošaljite nam screenshot rezultata ili PDF potvrdu. PDF sadrži naziv nagrade, vreme izvlačenja, Draw ID i maskirani token.',
          'Šta ako izgubim link?' => 'Kontaktirajte nas što pre. Ako Mystery Box nije otvoren, pomoći ćemo Vam da ponovo pristupite linku.',
          'Kome da se javim?' => 'Javite se PlayWorld podršci ili kontakt osobi koja Vam je poslala Mystery Box link.',
          'Da li su mogući naknadni povraćaj ili zamena?' => 'Ne. Mystery Box je proizvod iznenađenja, a rezultat postaje konačan nakon otvaranja. Naknadne zamene, refundacije i isplate u novcu nisu moguće, osim provere i eventualnog novog boxa u posebno navedenom slučaju duplikata igre kupljene preko PlayWorld-a.',
      ],
  ];
  ?>
  <div class="faq">
    <?php foreach ($sections as $sectionTitle => $items): ?>
      <section class="faq-section" aria-labelledby="faq-<?= e(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $sectionTitle))) ?>">
        <h2 id="faq-<?= e(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $sectionTitle))) ?>"><?= e($sectionTitle) ?></h2>
        <?php foreach ($items as $q => $a): ?>
          <details>
            <summary><?= e($q) ?></summary>
            <p><?= e($a) ?></p>
          </details>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
</section>
