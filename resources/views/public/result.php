<section class="result-shell <?= !empty($openedNow) ? 'celebrate' : '' ?>">
  <div class="box-animation" aria-hidden="true">
    <div class="box-lid"></div>
    <div class="box-body"></div>
    <div class="glow"></div>
  </div>
  <div class="panel result">
    <p class="eyebrow">Congratulations!</p>
    <img class="cover" src="<?= e($draw['cover_path']) ?>" alt="">
    <h1><?= e($draw['prize_name']) ?></h1>
    <p><?= e($draw['prize_description']) ?></p>
    <?php if (!empty($draw['prize_instructions'])): ?><p class="notice"><?= e($draw['prize_instructions']) ?></p><?php endif; ?>
    <dl class="meta">
      <div><dt>Status</dt><dd>Confirmed</dd></div>
      <div><dt>Datum i vreme</dt><dd><?= e(format_date($draw['created_at'])) ?></dd></div>
      <div><dt>Draw ID</dt><dd><?= e($draw['draw_id']) ?></dd></div>
    </dl>
    <a class="primary" href="/box/<?= e($token) ?>/certificate">Download PDF Certificate</a>
    <button class="ghost" type="button" data-sound-toggle>Sound on/off</button>
  </div>
</section>
