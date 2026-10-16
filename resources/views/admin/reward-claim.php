<?php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if ($claim):
    $st = claim_status((string) $claim['status']);
    $intl = phone_international((string) $claim['recipient_phone']);
    $message = 'Poštovani, javljamo se iz PlayWorld-a u vezi Vaše nagrade „' . $claim['prize_name'] . '” (Draw ID ' . $claim['draw_id'] . '). Kada Vam odgovara da dogovorimo isporuku igre?';
endif;
?>
<section class="panel narrow claim-detail">
  <div class="section-head">
    <div>
      <p class="eyebrow">Zahtev #<?= e((string) ($claim['id'] ?? '')) ?></p>
      <h1><?= e((string) ($claim['prize_name'] ?? 'Zahtev nije pronađen')) ?></h1>
    </div>
    <a class="ghost" href="/admin/reward-claims">Svi zahtevi</a>
  </div>
  <?php if (is_array($flash) && !empty($flash['message'])): ?>
    <p class="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'notice' ?>" role="status"><?= e((string) $flash['message']) ?></p>
  <?php endif; ?>
  <?php if ($claim): ?>
    <div class="claim-summary">
      <span class="chip <?= e($st['class']) ?>"><?= e($st['label']) ?></span>
      <span>Primljeno <?= e(format_date((string) $claim['created_at'])) ?></span>
      <code><?= e($claim['draw_id']) ?></code>
    </div>

    <h2 class="claim-h2">Kontakt kupca</h2>
    <dl class="meta claim-contact-info">
      <div><dt>Telefon / Viber</dt><dd><?= e($claim['recipient_phone']) ?></dd></div>
      <div><dt>Email</dt><dd><?= e($claim['recipient_email']) ?></dd></div>
    </dl>
    <div class="claim-contact-actions">
      <a class="claim-contact-btn is-call" href="tel:+<?= e($intl) ?>">Pozovi</a>
      <a class="claim-contact-btn is-viber" href="viber://chat?number=%2B<?= e($intl) ?>">Viber</a>
      <a class="claim-contact-btn is-whatsapp" href="https://wa.me/<?= e($intl) ?>?text=<?= e(rawurlencode($message)) ?>" target="_blank" rel="noopener">WhatsApp</a>
      <a class="claim-contact-btn is-mail" href="mailto:<?= e($claim['recipient_email']) ?>?subject=<?= e(rawurlencode('PlayWorld Mystery Box: isporuka nagrade')) ?>&body=<?= e(rawurlencode($message)) ?>">Email</a>
    </div>
    <div class="claim-message">
      <p><?= e($message) ?></p>
      <button type="button" class="ghost" data-copy="<?= e($message) ?>" data-copied-label="Poruka kopirana">Kopiraj poruku</button>
    </div>

    <h2 class="claim-h2">Status isporuke</h2>
    <form method="post" action="/admin/reward-claims/<?= e((string) $claim['id']) ?>/status" class="status-buttons">
      <?= csrf_field() ?>
      <?php foreach (['New', 'InProgress', 'Delivered', 'Cancelled'] as $option): $o = claim_status($option); ?>
        <button type="submit" name="status" value="<?= e($option) ?>" class="status-btn <?= e($o['class']) ?> <?= $claim['status'] === $option ? 'is-current' : '' ?>" <?= $claim['status'] === $option ? 'aria-current="true"' : '' ?>><?= e($o['label']) ?></button>
      <?php endforeach; ?>
    </form>
    <p class="muted claim-note">Telegram obaveštenje: <?= e($claim['notification_status']) ?></p>
  <?php else: ?>
    <p class="alert">Zahtev nije pronađen.</p>
  <?php endif; ?>
</section>
