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
    ? ($bonusCount === 1 ? 'Preuzmite besplatan box' : 'Preuzmite besplatne boxove')
    : 'Preuzmite nagradu';
?>
<section class="result-shell <?= !empty($openedNow) ? 'celebrate' : '' ?>">
  <?php if (!empty($openedNow)): ?>
    <div class="box-animation" aria-hidden="true">
      <div class="spark-field"></div>
      <div class="box-lid"></div>
      <div class="box-body"></div>
      <div class="glow"></div>
    </div>
  <?php endif; ?>
  <div class="panel result verified-result" data-watermark="DRAW ID <?= e($draw['draw_id']) ?>">
    <p class="eyebrow">Čestitamo! Osvojili ste</p>
    <?php $rarityKey = strtolower(str_replace(' ', '-', (string) ($draw['rarity'] ?? ''))); ?>
    <?php if (in_array($rarityKey, ['special', 'rare', 'ultra-rare'], true)): ?>
      <span class="rarity-chip rarity-<?= e($rarityKey) ?>"><?= e((string) $draw['rarity']) ?></span>
    <?php endif; ?>
    <div class="cover-stage rarity-<?= e($rarityKey) ?>"><img class="cover" src="<?= e($draw['cover_path']) ?>" alt=""></div>
    <h1 class="<?= mb_strlen((string) $draw['prize_name']) > 36 ? 'is-long' : '' ?>"><?= e($draw['prize_name']) ?></h1>
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
              <p class="notice warning-notice">Prethodni pokušaj slanja na <?= e((string) $bonusDelivery['masked_email']) ?> nije uspeo. Molimo Vas, proverite adresu i pokušajte ponovo. Isti linkovi će biti poslati, bez kreiranja novih boxova.</p>
            <?php endif; ?>
            <form method="post" action="/box/<?= e($token) ?>/bonus" class="claim-form action-form" data-action-form>
              <?= csrf_field() ?>
              <label class="field-highlight">Vaša email adresa<input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="ime@primer.com" value="<?= e((string) ($oldInput['email'] ?? $bonusDelivery['recipient_email'] ?? '')) ?>"></label>
              <label>Ime (opciono)<input type="text" name="name" maxlength="160" autocomplete="given-name" value="<?= e((string) ($oldInput['name'] ?? '')) ?>"></label>
              <button class="primary action-submit" type="submit"><?= $bonusDeliveryFailed ? 'Pošaljite linkove ponovo' : 'Pošaljite mi ' . ($bonusCount === 1 ? 'box' : 'boxove') ?></button>
            </form>
            <p class="action-hint">Molimo Vas, ne zatvarajte ovu stranicu dok ne pošaljete email. Ako poruka ne stigne u roku od nekoliko minuta, proverite folder Spam ili Promocije.</p>
          <?php else: ?>
            <span class="action-badge done-badge"><?= $bonusDeliverySent ? '✓ Poslato' : 'U obradi' ?></span>
            <h2>Još <?= e((string) $bonusCount) ?> <?= e($boxWord) ?> <?= $bonusDeliverySent ? 'na putu do Vas' : 'u obradi' ?></h2>
            <?php if ($bonusDeliverySent): ?>
              <p>Linkovi su poslati na <strong><?= e((string) $bonusDelivery['masked_email']) ?></strong>. Otvorite email od PlayWorld-a i kliknite na link da otvorite <?= $bonusCount === 1 ? 'novi box' : 'nove boxove' ?>. Ako je ne vidite, proverite folder Spam ili Promocije.</p>
            <?php elseif ($bonusDeliveryNeedsReview): ?>
              <p class="notice">Nismo dobili potvrdu o slanju na <?= e((string) $bonusDelivery['masked_email']) ?>. Molimo Vas, ne šaljite zahtev ponovo. Proverite folder Primljeno i Spam, a ako poruka ne stigne, kontaktirajte PlayWorld tim.</p>
            <?php else: ?>
              <p class="notice">Slanje na <?= e((string) $bonusDelivery['masked_email']) ?> je u toku. Molimo Vas, osvežite stranicu za nekoliko trenutaka.</p>
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
            <div class="secret-code"><code><?= e($discountCode['code']) ?></code><button type="button" class="ghost" data-copy="<?= e($discountCode['code']) ?>" data-copied-label="Kod kopiran">Kopirajte kod</button></div>
            <a class="primary discount-use-button" href="https://www.playworld.rs/" target="_blank" rel="noopener">Iskoristite kupon</a>
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
            <p>Zahtev je primljen za <strong><?= e((string) $rewardClaim['masked_email']) ?></strong>. <strong>Kontaktiraćemo Vas tokom radnog vremena</strong> na ostavljeni email ili telefon radi isporuke igre. Ne morate ništa više da radite.</p>
          <?php else: ?>
            <span class="action-badge">Poslednji korak</span>
            <h2>Preuzmite nagradu</h2>
            <p class="action-lead">Ostavite email i telefon. <strong>PlayWorld tim će Vas kontaktirati tokom radnog vremena</strong> radi isporuke igre.</p>
            <p class="action-reassure"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.500 4.500 4.500L19 7.500" fill="none" stroke="currentColor" stroke-width="2.600" stroke-linecap="round" stroke-linejoin="round"/></svg>Ne morate da šaljete screenshot ni PDF. Rezultat proveravamo sami.</p>
            <ol class="action-steps" aria-label="Kako da preuzmete nagradu">
              <li class="is-current"><span>1</span>Upišite email i telefon</li>
              <li><span>2</span>Kliknite dugme ispod</li>
              <li><span>3</span>Javljamo Vam se u radnom vremenu</li>
            </ol>
            <form method="post" action="/box/<?= e($token) ?>/claim" class="claim-form action-form" data-action-form>
              <?= csrf_field() ?>
              <label class="field-highlight">Email adresa<input type="email" name="email" required autocomplete="email" inputmode="email" placeholder="ime@primer.com" value="<?= e((string) ($oldInput['email'] ?? '')) ?>"></label>
              <label>Telefon / Viber<input type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="+381 60 123 4567" value="<?= e((string) ($oldInput['phone'] ?? '')) ?>"></label>
              <button class="primary action-submit" type="submit">Preuzmite nagradu</button>
            </form>
            <div class="contact-actions">
              <p class="contact-lead">Želite li radije Vi da nas kontaktirate?</p>
              <button class="ghost contact-toggle" type="button" data-contact-toggle aria-expanded="false" aria-controls="contact-options">Kontaktirajte nas</button>
              <div class="contact-icons" id="contact-options" data-contact-panel hidden>
                <?php require app_path('resources/views/partials/contact-icons.php'); ?>
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
      <div><dt>Status</dt><dd>Potvrđeno</dd></div>
      <div><dt>Datum i vreme</dt><dd><?= e(format_date($draw['created_at'])) ?></dd></div>
    </dl>
    <div class="result-actions">
      <div class="result-action-grid">
        <a class="result-btn <?= $hasPendingAction ? 'is-ghost' : 'is-primary' ?>" href="/box/<?= e($token) ?>/certificate">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10m0 0-4-4m4 4 4-4M5 19h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Preuzmite PDF potvrdu</span>
        </a>
        <a class="result-btn is-ghost" href="/verify?draw=<?= e($draw['draw_id']) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5.500c0 4.300 2.900 8 7 9.500 4.100-1.500 7-5.200 7-9.500V6l-7-3Zm-3 9 2.200 2.200L15.500 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Proverite Draw ID</span>
        </a>
        <a class="result-btn <?= $hasPendingAction ? 'is-ghost' : 'is-buy' ?>" href="https://www.playworld.rs/kupi/playworld-mystery-box-1004" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2.500l2 11h10l2-8H7M10 20h.01M17 20h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span>Kupite još jedan box</span>
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
