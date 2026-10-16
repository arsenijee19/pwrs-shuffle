<?php

/** @var array<int,string> $links */
/** @var string $recipientName */
$greeting = trim($recipientName) !== '' ? 'Poštovani/a ' . trim($recipientName) . ',' : 'Poštovani,';
?>
<!doctype html>
<html lang="sr">
<body style="margin:0;padding:0;background:#f3f6fb;color:#121826;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:28px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#fff;border:1px solid #dbe4ef;border-radius:14px;overflow:hidden;">
        <tr><td style="padding:34px 30px;background:#111827;color:#fff;">
          <div style="font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#7dd3fc;">PlayWorld Mystery Box</div>
          <h1 style="margin:10px 0 0;font-size:32px;line-height:1.1;">Vaši dodatni boxovi su spremni</h1>
        </td></tr>
        <tr><td style="padding:30px;">
          <p style="font-size:16px;line-height:1.6;color:#28384f;"><?= e($greeting) ?></p>
          <p style="font-size:16px;line-height:1.6;color:#28384f;">U nastavku se nalaze Vaši novi, jedinstveni Mystery Box linkovi. Svaki link važi za jedno otvaranje.</p>
          <?php foreach ($links as $index => $link): ?>
            <div style="margin:18px 0;padding:18px;border:1px solid #dbe4ef;border-radius:12px;background:#f8fafc;">
              <strong style="display:block;margin-bottom:12px;color:#121826;">Mystery Box <?= e((string) ($index + 1)) ?></strong>
              <a href="<?= e($link) ?>" style="display:inline-block;padding:13px 18px;background:#2457c5;border-radius:9px;color:#fff;text-decoration:none;font-weight:700;">Otvorite Mystery Box</a>
              <div style="margin-top:12px;word-break:break-all;font-size:12px;color:#667085;"><?= e($link) ?></div>
            </div>
          <?php endforeach; ?>
          <p style="font-size:14px;line-height:1.6;color:#17624a;background:#eef7f3;padding:14px 16px;border-radius:10px;">Nakon svakog otvaranja kliknite „Preuzmite nagradu“ i ostavite kontakt. Screenshot nije potreban, a naš tim će Vas kontaktirati tokom radnog vremena radi isporuke igre.</p>
        </td></tr>
        <tr><td style="padding:22px 30px;background:#f8fafc;border-top:1px solid #dbe4ef;color:#667085;font-size:13px;">Hvala Vam na učešću!<br><strong style="color:#121826;">PlayWorld tim</strong></td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
