<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Middleware;

final class RateLimiter
{
    public static function hit(string $key, bool $json = false, ?int $configuredLimit = null): void
    {
        $limit = $configuredLimit ?? (int) env_value('RATE_LIMIT_PER_MINUTE', 60);
        $limit = max(1, $limit);
        $ip = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $_SERVER['REMOTE_ADDR'] ?? 'cli');
        $file = app_path('storage/rate-limit/' . sha1($key . '|' . $ip) . '.json');
        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            http_response_code(503);
            if ($json) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'rate_limit_unavailable',
                        'message' => 'Zaštita od previše zahteva trenutno nije dostupna.',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                self::htmlMessage('Usluga trenutno nije dostupna.', 'Molimo Vas, pokušajte ponovo za nekoliko minuta.');
            }
            exit;
        }

        flock($handle, LOCK_EX);
        $now = time();
        $state = ['window' => $now, 'count' => 0];

        rewind($handle);
        $decoded = json_decode((string) stream_get_contents($handle), true);
        if (is_array($decoded)) {
            $state = $decoded;
        }

        if ($now - (int) ($state['window'] ?? 0) >= 60) {
            $state = ['window' => $now, 'count' => 0];
        }

        $state['count'] = (int) $state['count'] + 1;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($state['count'] > $limit) {
            http_response_code(429);
            if ($json) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'rate_limit_exceeded',
                        'message' => 'Previše zahteva. Molimo Vas, pokušajte ponovo kasnije.',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                self::htmlMessage('Previše zahteva.', 'Molimo Vas, sačekajte nekoliko trenutaka pa pokušajte ponovo.');
            }
            exit;
        }
    }

    private static function htmlMessage(string $title, string $text): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title>'
            . '<link rel="stylesheet" href="/assets/app.css"></head><body><main><section class="panel narrow"><p class="eyebrow">PlayWorld Mystery Box</p>'
            . '<h1>' . $title . '</h1><p class="lead">' . $text . '</p><a class="primary" href="/">Početna</a></section></main></body></html>';
    }
}
