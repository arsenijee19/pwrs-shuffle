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

        $html = '<html><head><style>
            body{font-family:DejaVu Sans,sans-serif;color:#14213d;margin:42px}
            .brand{font-size:24px;font-weight:700;margin-bottom:22px}
            .box{border:1px solid #dde3ed;border-radius:14px;padding:24px}
            .cover{width:160px;border-radius:12px;margin-bottom:16px}
            .qr{width:120px;margin-top:18px}
            .muted{color:#5f6c7b;font-size:13px;line-height:1.5}
            h1{font-size:28px;margin:0 0 12px}
            dt{font-weight:700;margin-top:12px}
            dd{margin:4px 0 0}
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
            <img class="qr" src="' . $qr . '">
            <p class="muted">Ovaj PDF predstavlja potvrdu rezultata Mystery Box izvlačenja.</p>
            <p class="muted">Rezultat čuvamo narednih 5 dana na platformi. Preporučujemo da sačuvate ovaj PDF za svoju evidenciju.</p>
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

