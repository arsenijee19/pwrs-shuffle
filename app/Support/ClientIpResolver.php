<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Support;

final class ClientIpResolver
{
    /** @return array{fingerprint:?string, applied:bool} */
    public function identity(): array
    {
        if (!(bool) env_value('ANTI_REPEAT_ENABLED', true)) {
            return ['fingerprint' => null, 'applied' => false];
        }

        $secret = trim((string) env_value('ANTI_REPEAT_SECRET', ''));
        $ip = $this->ip();
        if ($secret === '' || $ip === null) {
            return ['fingerprint' => null, 'applied' => false];
        }

        return [
            'fingerprint' => hash_hmac('sha256', $ip, $secret),
            'applied' => true,
        ];
    }

    private function ip(): ?string
    {
        $candidate = (bool) env_value('TRUST_CLOUDFLARE', false)
            ? ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '')
            : ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : null;
    }
}
