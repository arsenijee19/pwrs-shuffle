<?php
$bonusCount = (int) ($draw['bonus_box_count'] ?? 0);
$hasBonusBoxes = $bonusCount > 0;
$hasDiscount = (int) ($draw['discount_percent'] ?? 0) > 0;
$needsManualClaim = (int) ($draw['manual_claim'] ?? 0) === 1 || (!$hasBonusBoxes && !$hasDiscount) || ($hasDiscount && empty($discountCode));
$bonusDeliveryStatus = (string) ($bonusDelivery['status'] ?? '');
$bonusDeliverySent = $bonusDeliveryStatus === 'Sent';
$bonusDeliveryNeedsReview = $bonusDeliveryStatus === 'NeedsReview';
$bonusDeliverySending = $bonusDeliveryStatus === 'Sending';
$bonusDeliveryFailed = !empty($bonusDelivery) && !$bonusDeliverySent && !$bonusDeliveryNeedsReview && !$bonusDeliverySending;
$bonusPending = $hasBonusBoxes && (empty($bonusDelivery) || $bonusDeliveryFailed);
$claimPending = $needsManualClaim && empty($rewardClaim);
$hasPendingAction = $bonusPending || $claimPending;
$boxWord = $bonusCount === 1 ? 'Mystery Box' : ($bonusCount < 5 ? 'Mystery Boxa' : 'Mystery Boxova');
$stickyTarget = $bonusPending ? 'bonus-reward' : 'claim-reward';
$stickyLabel = $bonusPending
    ? ($bonusCount === 1 ? 'Preuzmi svoj besplatni box' : 'Preuzmi svoje besplatne boxove')
    : 'Preuzmi nagradu';
?>
<section class="result-shell <?= !empty($openedNow) ? 'celebrate' : '' ?>">
  <div class="box-animation" aria-hidden="true">
    <div class="spark-field"></div>
    <div class="box-lid"></div>
    <div class="box-body"></div>
    <div class="glow"></div>
  </div>
  <div class="panel result verified-result" data-watermark="DRAW ID <?= e($draw['draw_id']) ?>">
    <p class="eyebrow">Čestitamo! Osvojili ste</p>
    <img class="cover" src="<?= e($draw['cover_path']) ?>" alt="">
    <h1><?= e($draw['prize_name']) ?></h1>
    <p><?= e($draw['prize_description']) ?></p>
    <?php if (!empty($draw['prize_instructions'])): ?><p class="notice"><?= e($draw['prize_instructions']) ?></p><?php endif; ?>

    <div class="reward-actions" id="reward-actions">
      <?php if (!empty($_SESSION['flash'])): ?><div class="notice success-notice flash-notice" role="status"><?= e((string) $_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>

      <?php if ($hasBonusBoxes): ?>
        <section class="reward-block action-card bonus-card <?= $bonusPending ? 'is-pending' : 'is-done' ?>" id="bonus-reward" data-action-card>
          <?php if ($bonusPending): ?>
            <span class="action-badge">Važno · Box još nije preuzet</span>
            <h2>Osvojili ste još <?= e((string) $bonusCount) ?> <?= e($boxWord) ?>!</h2>
            <p class="action-lead">Da biste <?= $bonusCount === 1 ? 'ga' : 'ih' ?> dobili, unesite email ispod. <?= $bonusCount === 1 ? 'Link stiže' : 'Svi linkovi stižu' ?> u jednoj poruci.</p>
            <ol class="action-steps" aria-label="Kako da preuzmete box">
              <li class="is-current"><span>1</span>Upišite email</li>
              <li><span>2</span>Kliknite dugme ispod</li>
              <li><span>3</span>Otvorite link iz emaila</li>
            </ol>
            <?php if ($bonusDeliveryFailed): ?>
              <p class="notice warning-notice">Prethodni pokušaj slanja na <?= e((string) $bonusDelivery['masked_email']) ?> nije uspeo. Proverite adresu i pokušajte ponovo; isti linkovi će biti poslati bez kreiranja novih boxova.</p>
            <?php endif; ?>
            <form method="post" action="/box/<?= e($token) ?>/bonus" class="claim-form action-form" data-action-form>
              <?= csrf_field() ?>
              <label class="field-highlight">Vaša email adresa<input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="ime@primer.com" value="<?= e((string) ($bonusDelivery['recipient_email'] ?? '')) ?>"></label>
              <label>Ime (opciono)<input type="text" name="name" maxlength="160" autocomplete="given-name"></label>
              <button class="primary action-submit" type="submit"><?= $bonusDeliveryFailed ? 'Pošalji linkove ponovo' : 'Pošalji mi ' . ($bonusCount === 1 ? 'box' : 'boxove') ?></button>
            </form>
            <p class="action-hint">Ne zatvarajte ovu stranicu dok ne pošaljete email. Ako poruka ne stigne za par minuta, proverite Spam / Promocije.</p>
          <?php else: ?>
            <span class="action-badge done-badge"><?= $bonusDeliverySent ? '✓ Poslato' : 'U obradi' ?></span>
            <h2>Još <?= e((string) $bonusCount) ?> <?= e($boxWord) ?> <?= $bonusDeliverySent ? 'na putu do Vas' : 'u obradi' ?></h2>
            <?php if ($bonusDeliverySent): ?>
              <p>Linkovi su poslati na <strong><?= e((string) $bonusDelivery['masked_email']) ?></strong>. Otvorite email od PlayWorld-a i kliknite na link da otvorite <?= $bonusCount === 1 ? 'novi box' : 'nove boxove' ?>. Ako ga ne vidite, proverite Spam / Promocije.</p>
            <?php elseif ($bonusDeliveryNeedsReview): ?>
              <p class="notice">Email server nije potvrdio ishod slanja na <?= e((string) $bonusDelivery['masked_email']) ?>. Nemojte slati zahtev više puta; proverite Primljeno i Spam, a zatim kontaktirajte PlayWorld tim ako poruka nije stigla.</p>
            <?php else: ?>
              <p class="notice">Slanje na <?= e((string) $bonusDelivery['masked_email']) ?> je u toku. Osvežite stranicu za nekoliko trenutaka.</p>
            <?php endif; ?>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($hasDiscount): ?>
        <section class="reward-block action-card discount-block">
          <span class="action-badge discount-badge">Vaš popust</span>
          <h2><?= e((string) $draw['discount_percent']) ?>% popusta</h2>
          <?php if (!empty($discountCode)): ?>
            <p>Kod je rezervisan za ovaj Draw ID. Kopirajte ga i iskoristite na <strong>www.playworld.rs</strong> za igre i pretplate iz ponude.</p>
            <div class="secret-code"><code><?= e($discountCode['code']) ?></code><button type="button" class="ghost" data-copy="<?= e($discountCode['code']) ?>">Kopiraj kod</button></div>
            <a class="primary discount-use-button" href="https://www.playworld.rs/" target="_blank" rel="noopener">Iskoristi kupon</a>
          <?php else: ?>
            <p class="notice">Popust možete iskoristiti na <strong>www.playworld.rs</strong>. Kod trenutno nije dostupan za prikaz; ostavite kontakt u odeljku za preuzimanje nagrade.</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($needsManualClaim): ?>
        <section class="reward-block action-card claim-block <?= $claimPending ? 'is-pending' : 'is-done' ?>" id="claim-reward" data-action-card>
          <?php if (!$claimPending): ?>
            <span class="action-badge done-badge">✓ Zahtev primljen</span>
            <h2>Nagrada je prijavljena</h2>
            <p>Zahtev je primljen za <strong><?= e((string) $rewardClaim['masked_email']) ?></strong>. Član PlayWorld tima će Vas kontaktirati na ostavljeni email ili telefon radi dogovora oko preuzimanja nagrade.</p>
          <?php else: ?>
            <span class="action-badge">Poslednji korak</span>
            <h2>Preuzmite nagradu</h2>
            <p class="action-lead">Ostavite email i telefon, PlayWorld tim će Vas kontaktirati i dogovoriti preuzimanje. Screenshot ili PDF nisu potrebni, rezultat proveravamo po Draw ID-ju.</p>
            <ol class="action-steps" aria-label="Kako da preuzmete nagradu">
              <li class="is-current"><span>1</span>Upišite email i telefon</li>
              <li><span>2</span>Kliknite dugme ispod</li>
              <li><span>3</span>Javljamo Vam se</li>
            </ol>
            <form method="post" action="/box/<?= e($token) ?>/claim" class="claim-form action-form" data-action-form>
              <?= csrf_field() ?>
              <label class="field-highlight">Email adresa<input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="ime@primer.com"></label>
              <label>Telefon / Viber<input type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="+381 60 123 4567"></label>
              <button class="primary action-submit" type="submit">Preuzmi nagradu</button>
            </form>
            <div class="contact-actions">
              <p class="contact-lead">Radije biste nam se Vi javili?</p>
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

    <div class="verification-strip">
      <span>Verifikovan rezultat</span>
      <strong><?= e($draw['draw_id']) ?></strong>
    </div>
    <dl class="meta">
      <div><dt>Status</dt><dd>Confirmed</dd></div>
      <div><dt>Datum i vreme</dt><dd><?= e(format_date($draw['created_at'])) ?></dd></div>
      <div><dt>Draw ID</dt><dd><?= e($draw['draw_id']) ?></dd></div>
    </dl>
    <div class="result-actions">
      <div class="result-action-grid">
        <a class="<?= $hasPendingAction ? 'ghost' : 'primary' ?>" href="/box/<?= e($token) ?>/certificate">Preuzmi PDF potvrdu</a>
        <a class="ghost" href="/verify?draw=<?= e($draw['draw_id']) ?>">Proveri Draw ID</a>
        <a class="secondary-buy" href="https://www.playworld.rs/kupi/playworld-mystery-box-1004" target="_blank" rel="noopener">Kupi još jedan box</a>
      </div>
      <div class="result-action-controls">
        <button class="ghost" type="button" data-sound-toggle>Sound on/off</button>
      </div>
    </div>
  </div>
</section>
<?php if ($hasPendingAction): ?>
  <div class="sticky-action" data-sticky-action data-target="<?= e($stickyTarget) ?>" hidden>
    <a class="primary sticky-action-button" href="#<?= e($stickyTarget) ?>">
      <span><?= e($stickyLabel) ?></span>
      <svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M10 3v12m0 0-5-5m5 5 5-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
  </div>
<?php endif; ?>
