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
            return;
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
                        'message' => 'Previše zahteva. Pokušajte ponovo kasnije.',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo 'Too many requests. Please try again soon.';
            }
            exit;
        }
    }
}
