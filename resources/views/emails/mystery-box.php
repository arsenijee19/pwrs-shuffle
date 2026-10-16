<?php

/** @var string $link */
/** @var string $recipientName */

$safeName = trim($recipientName);
$greeting = $safeName !== '' ? 'Poštovani/a ' . $safeName . ',' : 'Poštovani,';
?>
<!doctype html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PlayWorld Mystery Box</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;color:#121826;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #dbe4ef;border-radius:14px;overflow:hidden;">
          <tr>
            <td style="padding:34px 30px;background:#111827;color:#ffffff;">
              <div style="font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#7dd3fc;">PlayWorld Mystery Box</div>
              <h1 style="margin:10px 0 0;font-size:34px;line-height:1.08;color:#ffffff;">Vaš box je spreman</h1>
              <p style="margin:14px 0 0;font-size:16px;line-height:1.6;color:#d7deea;">Kliknite na dugme ispod i otvorite svoj jedinstveni Mystery Box link.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:30px;">
              <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#28384f;"><?= e($greeting) ?></p>
              <p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#28384f;">Šaljemo Vam jedinstveni link za PlayWorld Mystery Box. Link važi za jedno otvaranje, a rezultat se nakon otvaranja čuva u PlayWorld evidenciji.</p>
              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0;">
                <tr>
                  <td style="background:#2457c5;border-radius:10px;">
                    <a href="<?= e($link) ?>" style="display:inline-block;padding:16px 26px;color:#ffffff;text-decoration:none;font-size:17px;font-weight:800;">Otvorite Mystery Box</a>
                  </td>
                </tr>
              </table>
              <div style="background:#eef7f3;border:1px solid #cfeade;border-radius:12px;padding:16px 18px;margin:0 0 24px;">
                <p style="margin:0;font-size:15px;line-height:1.55;color:#17624a;">Mystery Box možete otvoriti samo jednom. Nakon otvaranja kliknite „Preuzmite nagradu“ na stranici rezultata i ostavite kontakt. Screenshot nije potreban, a naš tim će Vas kontaktirati tokom radnog vremena radi isporuke igre.</p>
              </div>
              <h2 style="margin:0 0 12px;font-size:18px;color:#121826;">Kratki koraci</h2>
              <ol style="margin:0 0 24px;padding-left:20px;color:#465266;font-size:15px;line-height:1.7;">
                <li>Otvorite link iz ovog emaila.</li>
                <li>Pročitajte kratke instrukcije na stranici.</li>
                <li>Kliknite na dugme „Otvorite Mystery Box“.</li>
                <li>Kliknite „Preuzmite nagradu“ i ostavite email i telefon.</li>
                <li>Javićemo Vam se tokom radnog vremena radi isporuke igre.</li>
              </ol>
              <p style="margin:0 0 8px;font-size:13px;line-height:1.55;color:#667085;">Ako dugme ne radi, kopirajte ovaj link u browser:</p>
              <p style="margin:0;font-size:13px;line-height:1.55;word-break:break-all;color:#2457c5;"><a href="<?= e($link) ?>" style="color:#2457c5;"><?= e($link) ?></a></p>
            </td>
          </tr>
          <tr>
            <td style="padding:22px 30px;background:#f8fafc;border-top:1px solid #dbe4ef;color:#667085;font-size:13px;line-height:1.55;">
              Hvala Vam na učešću i želimo Vam puno sreće!<br>
              <strong style="color:#121826;">PlayWorld tim</strong>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
