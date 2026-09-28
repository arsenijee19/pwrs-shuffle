<?php
$hasBonusBoxes = (int) ($draw['bonus_box_count'] ?? 0) > 0;
$hasDiscount = (int) ($draw['discount_percent'] ?? 0) > 0;
$needsManualClaim = (int) ($draw['manual_claim'] ?? 0) === 1 || (!$hasBonusBoxes && !$hasDiscount) || ($hasDiscount && empty($discountCode));
$bonusDeliveryStatus = (string) ($bonusDelivery['status'] ?? '');
$bonusDeliverySent = $bonusDeliveryStatus === 'Sent';
$bonusDeliveryNeedsReview = $bonusDeliveryStatus === 'NeedsReview';
$bonusDeliverySending = $bonusDeliveryStatus === 'Sending';
?>
<section class="result-shell <?= !empty($openedNow) ? 'celebrate' : '' ?>">
  <div class="box-animation" aria-hidden="true">
    <div class="spark-field"></div>
    <div class="box-lid"></div>
    <div class="box-body"></div>
    <div class="glow"></div>
  </div>
  <div class="panel result verified-result" data-watermark="DRAW ID <?= e($draw['draw_id']) ?>">
    <p class="eyebrow">Congratulations!</p>
    <div class="verification-strip">
      <span>Verifikovan rezultat</span>
      <strong><?= e($draw['draw_id']) ?></strong>
    </div>
    <?php if ($needsManualClaim): ?>
      <div class="result-claim-priority">
        <a class="primary result-claim-cta" href="#claim-reward">Preuzmi nagradu</a>
        <p>Ostavite kontakt podatke i PlayWorld tim će Vas kontaktirati.</p>
      </div>
    <?php endif; ?>
    <img class="cover" src="<?= e($draw['cover_path']) ?>" alt="">
    <h1><?= e($draw['prize_name']) ?></h1>
    <p><?= e($draw['prize_description']) ?></p>
    <?php if (!empty($draw['prize_instructions'])): ?><p class="notice"><?= e($draw['prize_instructions']) ?></p><?php endif; ?>
    <?php if (!empty($_SESSION['flash'])): ?><div class="notice success-notice"><?= e((string) $_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
    <dl class="meta">
      <div><dt>Status</dt><dd>Confirmed</dd></div>
      <div><dt>Datum i vreme</dt><dd><?= e(format_date($draw['created_at'])) ?></dd></div>
      <div><dt>Draw ID</dt><dd><?= e($draw['draw_id']) ?></dd></div>
    </dl>
    <div class="result-actions <?= $needsManualClaim ? 'has-claim' : 'no-claim' ?>">
      <div class="result-action-grid">
        <a class="primary" href="/box/<?= e($token) ?>/certificate">Download PDF Certificate</a>
        <a class="ghost" href="/verify?draw=<?= e($draw['draw_id']) ?>">Proveri Draw ID</a>
        <a class="secondary-buy" href="https://www.playworld.rs/kupi/playworld-mystery-box-1004" target="_blank" rel="noopener">Kupi još jedan box</a>
      </div>
      <div class="result-action-controls">
        <button class="ghost" type="button" data-sound-toggle>Sound on/off</button>
      </div>
    </div>
    <?php if ($hasDiscount): ?>
      <section class="reward-block discount-block">
        <p class="eyebrow">Vaš popust</p>
        <h2><?= e((string) $draw['discount_percent']) ?>% popusta</h2>
        <?php if (!empty($discountCode)): ?>
          <p>Kod je rezervisan za ovaj Draw ID. Sačuvajte ga i iskoristite na <strong>www.playworld.rs</strong> za igre i pretplate iz ponude.</p>
          <div class="secret-code"><code><?= e($discountCode['code']) ?></code><button type="button" class="ghost" data-copy="<?= e($discountCode['code']) ?>">Kopiraj kod</button></div>
          <a class="primary discount-use-button" href="https://www.playworld.rs/" target="_blank" rel="noopener">Iskoristi kupon</a>
        <?php else: ?>
          <p class="notice">Popust možete iskoristiti na <strong>www.playworld.rs</strong>. Kod trenutno nije dostupan za prikaz; ostavite kontakt u odeljku za preuzimanje nagrade.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <?php if ($hasBonusBoxes): ?>
      <section class="reward-block">
        <p class="eyebrow">Dodatni Mystery Box linkovi</p>
        <h2>Osvojili ste još <?= e((string) $draw['bonus_box_count']) ?> box<?= (int) $draw['bonus_box_count'] === 1 ? '' : 'a' ?></h2>
        <?php if ($bonusDeliverySent): ?>
          <p class="notice success-notice">Linkovi su poslati na <?= e((string) $bonusDelivery['masked_email']) ?> kroz PlayWorld email sistem.</p>
        <?php elseif ($bonusDeliveryNeedsReview): ?>
          <p class="notice">Email server nije potvrdio ishod slanja na <?= e((string) $bonusDelivery['masked_email']) ?>. Nemojte slati zahtev više puta; proverite Primljeno i Spam, a zatim kontaktirajte PlayWorld tim ako poruka nije stigla.</p>
        <?php elseif ($bonusDeliverySending): ?>
          <p class="notice">Slanje na <?= e((string) $bonusDelivery['masked_email']) ?> je u toku. Osvežite stranicu za nekoliko trenutaka.</p>
        <?php elseif (!empty($bonusDelivery)): ?>
          <p class="notice">Prethodni pokušaj slanja na <?= e((string) $bonusDelivery['masked_email']) ?> nije uspeo. Proverite adresu i pokušajte ponovo; isti linkovi će biti poslati bez kreiranja novih boxova.</p>
          <form method="post" action="/box/<?= e($token) ?>/bonus" class="claim-form">
            <?= csrf_field() ?>
            <label>Email adresa<input type="email" name="email" required autocomplete="email" value="<?= e((string) $bonusDelivery['recipient_email']) ?>"></label>
            <label>Ime <span>opciono</span><input type="text" name="name" maxlength="160"></label>
            <button class="primary" type="submit">Pošalji linkove ponovo</button>
          </form>
        <?php else: ?>
          <p>Unesite email na koji želite da primite sve dodatne linkove u jednoj poruci.</p>
          <form method="post" action="/box/<?= e($token) ?>/bonus" class="claim-form">
            <?= csrf_field() ?>
            <label>Email adresa<input type="email" name="email" required autocomplete="email"></label>
            <label>Ime <span>opciono</span><input type="text" name="name" maxlength="160"></label>
            <button class="primary" type="submit">Pošalji mi boxove</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <?php if ($needsManualClaim): ?>
      <section class="reward-block claim-block" id="claim-reward">
        <p class="eyebrow">Preuzimanje nagrade</p>
        <h2>Preuzmite poklon</h2>
        <?php if (!empty($rewardClaim)): ?>
          <p>Zahtev je primljen za <?= e((string) $rewardClaim['masked_email']) ?>. Član PlayWorld tima će Vas kontaktirati na ostavljeni email ili telefon radi dogovora oko preuzimanja nagrade.</p>
        <?php else: ?>
          <p>Ostavite kontakt podatke kako bi Vas član PlayWorld tima kontaktirao i organizovao preuzimanje nagrade. Nije potrebno da uz prijavu šaljete screenshot ili PDF potvrdu — rezultat proveravamo pomoću Draw ID-ja.</p>
          <form method="post" action="/box/<?= e($token) ?>/claim" class="claim-form">
            <?= csrf_field() ?>
            <label>Email adresa<input type="email" name="email" required autocomplete="email"></label>
            <label>Telefon / Viber<input type="tel" name="phone" required inputmode="tel" placeholder="+381 60 123 4567"></label>
            <button class="primary" type="submit">Pošalji podatke za preuzimanje</button>
          </form>
          <p class="claim-followup">Nakon slanja, javiće Vam se član PlayWorld tima na ostavljeni email ili telefon.</p>
          <div class="contact-actions">
            <button class="ghost contact-toggle" type="button" data-contact-toggle aria-expanded="false" aria-controls="contact-options">KONTAKT</button>
            <div class="contact-options" id="contact-options" data-contact-panel hidden>
              <a class="contact-option contact-option-email" href="mailto:usluge@playworld.rs?subject=PlayWorld%20Mystery%20Box%20-%20Preuzimanje%20nagrade">
                <span class="contact-copy"><strong>Email</strong><span>usluge@playworld.rs</span></span>
              </a>
              <a class="contact-option contact-option-instagram" href="https://www.instagram.com/playworldrs/" target="_blank" rel="noopener">
                <span class="contact-copy"><strong>Instagram</strong><span>@playworldrs</span></span>
              </a>
              <a class="contact-option contact-option-viber" href="viber://chat?number=%2B381655102425">
                <span class="contact-copy"><strong>Viber</strong><span>+381 65 510 2425</span></span>
              </a>
              <a class="contact-option contact-option-whatsapp" href="https://wa.me/381655102425" target="_blank" rel="noopener">
                <span class="contact-copy"><strong>WhatsApp</strong><span>+381 65 510 2425</span></span>
              </a>
              <a class="contact-option contact-option-viber" href="viber://chat?number=%2B381606121906">
                <span class="contact-copy"><strong>Viber</strong><span>+381 60 612 1906</span></span>
              </a>
              <a class="contact-option contact-option-whatsapp" href="https://wa.me/381606121906" target="_blank" rel="noopener">
                <span class="contact-copy"><strong>WhatsApp</strong><span>+381 60 612 1906</span></span>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</section>
