<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Middleware;

final class RateLimiter
{
    public static function hit(string $key): void
    {
        $limit = (int) env_value('RATE_LIMIT_PER_MINUTE', 60);
        $ip = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $_SERVER['REMOTE_ADDR'] ?? 'cli');
        $file = app_path('storage/rate-limit/' . sha1($key . '|' . $ip) . '.json');
        $now = time();
        $state = ['window' => $now, 'count' => 0];

        if (file_exists($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        if ($now - (int) ($state['window'] ?? 0) >= 60) {
            $state = ['window' => $now, 'count' => 0];
        }

        $state['count'] = (int) $state['count'] + 1;
        file_put_contents($file, json_encode($state), LOCK_EX);

        if ($state['count'] > $limit) {
            http_response_code(429);
            echo 'Too many requests. Please try again soon.';
            exit;
        }
    }
}

