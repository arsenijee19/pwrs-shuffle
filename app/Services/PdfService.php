<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use chillerlan\QRCode\QRCode;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfService
{
    public function output(array $draw, string $resultUrl): void
    {
        $qr = (new QRCode())->render($resultUrl);
        $coverPath = app_path('public' . $draw['cover_path']);
        $cover = file_exists($coverPath) ? 'data:' . mime_content_type($coverPath) . ';base64,' . base64_encode((string) file_get_contents($coverPath)) : '';

        $bonus = (int) ($draw['bonus_box_count'] ?? 0);
        $needsClaim = (int) ($draw['manual_claim'] ?? 0) === 1 || ($bonus < 1 && (int) ($draw['discount_percent'] ?? 0) < 1);
        if ($needsClaim) {
            $nextTitle = 'Sledeći korak: preuzmite nagradu';
            $nextText = 'Kliknite na dugme ispod ili skenirajte QR kod, pa pritisnite „Preuzmite nagradu“ i ostavite email i telefon. '
                . 'Screenshot nije potreban. <strong>Kontaktiraćemo Vas tokom radnog vremena radi isporuke igre.</strong>';
            $nextButton = 'Preuzmite nagradu';
        } elseif ($bonus > 0) {
            $nextTitle = 'Sledeći korak: preuzmite dodatne boxove';
            $nextText = 'Otvorite link ispod i unesite email na koji želite da primite dodatne Mystery Box linkove. Screenshot nije potreban.';
            $nextButton = 'Preuzmite dodatne boxove';
        } else {
            $nextTitle = 'Sledeći korak: iskoristite popust';
            $nextText = 'Otvorite link ispod da vidite svoj kod za popust i iskoristite ga na www.playworld.rs. Screenshot nije potreban.';
            $nextButton = 'Prikažite moj popust';
        }

        $html = '<html><head><style>
            body{font-family:DejaVu Sans,sans-serif;color:#14213d;margin:30px 36px}
            .brand{font-size:22px;font-weight:700;margin-bottom:16px}
            .box{border:1px solid #dde3ed;border-radius:14px;padding:20px 24px}
            .cover{width:120px;border-radius:12px;margin-bottom:10px}
            .qr{width:110px;margin-top:14px}
            .muted{color:#5f6c7b;font-size:13px;line-height:1.5}
            h1{font-size:24px;margin:0 0 8px}
            dt{font-weight:700;margin-top:8px;font-size:13px}
            dd{margin:4px 0 0}
            .next{margin-top:16px;padding:18px 20px;border-radius:12px;background:#eef2ff;border:1px solid #c9d4f7}
            .next h2{font-size:16px;margin:0 0 8px;color:#1d3a8a}
            .next p{margin:0 0 14px;font-size:13px;line-height:1.55;color:#28384f}
            .btn{display:block;width:260px;padding:13px 0;border-radius:9px;background:#2457c5;color:#ffffff;text-align:center;text-decoration:none;font-size:15px;font-weight:700}
        </style></head><body>
            <div class="brand">PlayWorld Mystery Box</div>
            <div class="box">'
            . ($cover ? '<img class="cover" src="' . $cover . '">' : '') .
            '<h1>' . e($draw['prize_name']) . '</h1>
            <p>' . e($draw['prize_description']) . '</p>
            <dl>
                <dt>Datum i vreme</dt><dd>' . e(format_date($draw['created_at'])) . '</dd>
                <dt>Draw ID</dt><dd>' . e($draw['draw_id']) . '</dd>
                <dt>Token</dt><dd>' . e($draw['token_mask']) . '</dd>
            </dl>
            <div class="next">
                <h2>' . $nextTitle . '</h2>
                <p>' . $nextText . '</p>
                <a class="btn" href="' . e($resultUrl) . '">' . $nextButton . '</a>
            </div>
            <img class="qr" src="' . $qr . '">
            <p class="muted">Ovaj PDF je potvrda rezultata Mystery Box izvlačenja. Rezultat je dostupan na linku narednih 5 dana, zato nagradu preuzmite što pre.</p>
            </div>
        </body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        $dompdf->stream('playworld-mystery-box-' . $draw['draw_id'] . '.pdf', ['Attachment' => true]);
    }
}

