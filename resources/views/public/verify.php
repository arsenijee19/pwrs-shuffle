<section class="panel readable verify-panel">
  <div class="verify-intro">
    <div>
      <p class="eyebrow">Provera rezultata</p>
      <h1>Proverite Draw ID</h1>
      <p class="lead">Unesite Draw ID sa stranice rezultata ili PDF potvrde i proverite zvanično zabeleženu nagradu, datum i vreme izvlačenja.</p>
    </div>
    <div class="verify-mark" aria-hidden="true">PW</div>
  </div>

  <form class="verify-form" method="get" action="/verify">
    <label>
      <span>Draw ID</span>
      <input name="draw" value="<?= e($drawId ?? '') ?>" placeholder="PW-XXXXXXXXXX" autocomplete="off" inputmode="latin">
    </label>
    <button class="primary" type="submit">Proveri rezultat</button>
  </form>

  <?php if (!empty($searched) && empty($draw)): ?>
    <div class="alert verify-message">
      Ovaj Draw ID nije pronađen u PlayWorld Mystery Box evidenciji. Proverite da li je kod ispravno unet ili zatražite PDF potvrdu rezultata.
    </div>
  <?php endif; ?>

  <?php if (!empty($draw)): ?>
    <div class="verification-card">
      <div class="verification-card-head">
        <div>
          <span>Validan rezultat</span>
          <strong><?= e($draw['draw_id']) ?></strong>
        </div>
        <p>Podaci su pronađeni u PlayWorld evidenciji.</p>
      </div>
      <dl class="meta">
        <div><dt>Nagrada</dt><dd><?= e($draw['prize_name']) ?></dd></div>
        <div><dt>Datum i vreme</dt><dd><?= e(format_date($draw['created_at'])) ?></dd></div>
      </dl>
      <p class="notice">Uporedite ove podatke sa screenshotom ili PDF potvrdom. Validan rezultat mora imati isti Draw ID, naziv nagrade, datum i vreme.</p>
    </div>
  <?php endif; ?>
</section>
