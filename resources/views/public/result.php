<?php
$hasBonusBoxes = (int) ($draw['bonus_box_count'] ?? 0) > 0;
$hasDiscount = (int) ($draw['discount_percent'] ?? 0) > 0;
$needsManualClaim = (int) ($draw['manual_claim'] ?? 0) === 1 || (!$hasBonusBoxes && !$hasDiscount) || ($hasDiscount && empty($discountCode));
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
    <div class="result-actions">
      <a class="primary" href="/box/<?= e($token) ?>/certificate">Download PDF Certificate</a>
      <a class="ghost" href="/verify?draw=<?= e($draw['draw_id']) ?>">Proveri Draw ID</a>
      <a class="secondary-buy" href="https://www.playworld.rs/kupi/playworld-mystery-box-1004" target="_blank" rel="noopener">Kupi još jedan box</a>
      <?php if ($needsManualClaim): ?><a class="ghost result-claim-cta" href="#claim-reward">Preuzmi nagradu</a><?php endif; ?>
      <button class="ghost" type="button" data-sound-toggle>Sound on/off</button>
    </div>
    <?php if ($hasDiscount): ?>
      <section class="reward-block discount-block">
        <p class="eyebrow">Vaš popust</p>
        <h2><?= e((string) $draw['discount_percent']) ?>% popusta</h2>
        <?php if (!empty($discountCode)): ?>
          <p>Kod je rezervisan za ovaj Draw ID. Sačuvajte ga i pratite instrukcije PlayWorld tima.</p>
          <div class="secret-code"><code><?= e($discountCode['code']) ?></code><button type="button" class="ghost" data-copy="<?= e($discountCode['code']) ?>">Kopiraj kod</button></div>
        <?php else: ?>
          <p class="notice">Popust je deo osvojene nagrade. Kod trenutno nije dostupan za prikaz; ostavite kontakt u odeljku za preuzimanje nagrade.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <?php if ($hasBonusBoxes): ?>
      <section class="reward-block">
        <p class="eyebrow">Dodatni Mystery Box linkovi</p>
        <h2>Osvojili ste još <?= e((string) $draw['bonus_box_count']) ?> box<?= (int) $draw['bonus_box_count'] === 1 ? '' : 'a' ?></h2>
        <?php if (!empty($bonusDelivery)): ?>
          <p>Linkovi su poslati na <?= e((string) $bonusDelivery['masked_email']) ?>. Ovaj zahtev je već obrađen.</p>
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
          <p>Zahtev je primljen za <?= e((string) $rewardClaim['masked_email']) ?>. PlayWorld tim će Vam se javiti na ostavljeni email ili telefon.</p>
        <?php else: ?>
          <p>Unesite kontakt podatke. Nema potrebe da šaljete screenshot ili PDF potvrdu kroz poruku.</p>
          <form method="post" action="/box/<?= e($token) ?>/claim" class="claim-form">
            <?= csrf_field() ?>
            <label>Email adresa<input type="email" name="email" required autocomplete="email"></label>
            <label>Telefon / Viber<input type="tel" name="phone" required inputmode="tel" placeholder="+381 60 123 4567"></label>
            <button class="primary" type="submit">Pošalji podatke za preuzimanje</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</section>
