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
$flashType = (string) ($_SESSION['flash_type'] ?? 'success');
$oldInput = is_array($_SESSION['old_input'] ?? null) ? $_SESSION['old_input'] : [];
unset($_SESSION['old_input']);
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
      <?php if (!empty($_SESSION['flash'])): ?><div class="notice flash-notice <?= $flashType === 'error' ? 'error-notice' : 'success-notice' ?>" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>"><?= e((string) $_SESSION['flash']) ?></div><?php unset($_SESSION['flash'], $_SESSION['flash_type']); endif; ?>

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
              <label class="field-highlight">Vaša email adresa<input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="ime@primer.com" value="<?= e((string) ($oldInput['email'] ?? $bonusDelivery['recipient_email'] ?? '')) ?>"></label>
              <label>Ime (opciono)<input type="text" name="name" maxlength="160" autocomplete="given-name" value="<?= e((string) ($oldInput['name'] ?? '')) ?>"></label>
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
              <label class="field-highlight">Email adresa<input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="ime@primer.com" value="<?= e((string) ($oldInput['email'] ?? '')) ?>"></label>
              <label>Telefon / Viber<input type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="+381 60 123 4567" value="<?= e((string) ($oldInput['phone'] ?? '')) ?>"></label>
              <button class="primary action-submit" type="submit">Preuzmi nagradu</button>
            </form>
            <div class="contact-actions">
              <p class="contact-lead">Radije biste nam se Vi javili?</p>
              <button class="ghost contact-toggle" type="button" data-contact-toggle aria-expanded="false" aria-controls="contact-options">KONTAKT</button>
              <div class="contact-icons" id="contact-options" data-contact-panel hidden>
                <a class="contact-icon" href="mailto:usluge@playworld.rs?subject=PlayWorld%20Mystery%20Box%20-%20Preuzimanje%20nagrade" aria-label="Email: usluge@playworld.rs" title="Email">
                  <svg viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="gm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5ECBFF"/><stop offset="1" stop-color="#1676F3"/></linearGradient></defs><rect width="64" height="64" rx="15" fill="url(#gm)"/><rect x="12" y="18" width="40" height="28" rx="5" fill="#fff"/><path d="M14 22.5 32 36l18-13.5" fill="none" stroke="#1676F3" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <a class="contact-icon" href="https://www.instagram.com/playworldrs/" target="_blank" rel="noopener" aria-label="Instagram: @playworldrs" title="Instagram">
                  <svg viewBox="0 0 64 64" aria-hidden="true"><defs><radialGradient id="gi" cx="0.3" cy="1.05" r="1.25"><stop offset="0" stop-color="#FFD35C"/><stop offset="0.25" stop-color="#FF8A2B"/><stop offset="0.5" stop-color="#EE2A7B"/><stop offset="0.78" stop-color="#9B35C8"/><stop offset="1" stop-color="#5B51D8"/></radialGradient></defs><rect width="64" height="64" rx="15" fill="url(#gi)"/><rect x="16" y="16" width="32" height="32" rx="9" fill="none" stroke="#fff" stroke-width="3.4"/><circle cx="32" cy="32" r="7.5" fill="none" stroke="#fff" stroke-width="3.4"/><circle cx="41.6" cy="22.4" r="2.2" fill="#fff"/></svg>
                </a>
                <a class="contact-icon" href="https://wa.me/381655102425" target="_blank" rel="noopener" aria-label="WhatsApp: +381 65 510 2425" title="WhatsApp">
                  <svg viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" rx="15" fill="#25D366"/><g transform="translate(11 11) scale(1.833)"><path fill="#fff" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></g></svg>
                </a>
                <a class="contact-icon" href="viber://chat?number=%2B381655102425" aria-label="Viber: +381 65 510 2425" title="Viber">
                  <svg viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" rx="15" fill="#7360F2"/><path fill="#fff" d="M32 11c-10.5 0-18 6.2-18 16.5 0 6.4 3 11 8 13.6V50l7.4-6.6c.9.1 1.7.1 2.6.1 10.5 0 18-6.2 18-16.5S42.500 11 32 11Z"/><g transform="translate(19.500 15.500) scale(1.04)"><path fill="#7360F2" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.610-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" transform="scale(.82)"/></g></svg>
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
        <a class="result-btn <?= $hasPendingAction ? 'is-ghost' : 'is-primary' ?>" href="/box/<?= e($token) ?>/certificate">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10m0 0-4-4m4 4 4-4M5 19h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Preuzmi PDF potvrdu</span>
        </a>
        <a class="result-btn is-ghost" href="/verify?draw=<?= e($draw['draw_id']) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5.500c0 4.300 2.900 8 7 9.500 4.100-1.500 7-5.200 7-9.500V6l-7-3Zm-3 9 2.200 2.200L15.500 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Proveri Draw ID</span>
        </a>
        <a class="result-btn is-buy" href="https://www.playworld.rs/kupi/playworld-mystery-box-1004" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2.500l2 11h10l2-8H7M10 20h.01M17 20h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Kupi još jedan box</span>
        </a>
      </div>
      <div class="result-action-controls">
        <button class="sound-toggle" type="button" data-sound-toggle aria-pressed="true">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.500v5h3.500L12 18.500v-13L7.500 9.500H4Z" fill="currentColor"/><path data-sound-waves d="M15.500 9a4.500 4.500 0 0 1 0 6M18 6.500a8 8 0 0 1 0 11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span data-sound-label>Zvuk uključen</span>
        </button>
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
