<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use Throwable;

final class N8nRewardClaimsApi
{
    public function __construct(private readonly ?RewardClaimService $claims = null)
    {
    }

    /** @param array<string,string> $headers @return array{status:int,body:array<string,mixed>} */
    public function lease(string $method, array $headers, string $body): array
    {
        if (strtoupper($method) !== 'POST') {
            return $this->error(405, 'method_not_allowed', 'Dozvoljen je samo POST zahtev.');
        }
        if (!$this->authorized($headers) || !(bool) env_value('API_ENABLED', false)) {
            return $this->error(401, 'unauthorized', 'API autorizacija nije uspešna.');
        }
        $payload = $this->json($body);
        if ($payload === null) {
            return $this->error(400, 'invalid_json', 'Telo zahteva nije validan JSON.');
        }

        try {
            $rows = ($this->claims ?? new RewardClaimService())->lease((int) ($payload['limit'] ?? 20), (string) ($payload['worker'] ?? 'telegram'));
            return ['status' => 200, 'body' => ['status' => 'ok', 'claims' => $rows]];
        } catch (Throwable $error) {
            return $this->error(422, 'invalid_request', $error->getMessage());
        }
    }

    /** @param array<string,string> $headers @return array{status:int,body:array<string,mixed>} */
    public function notificationResult(int $id, string $method, array $headers, string $body): array
    {
        if (strtoupper($method) !== 'POST') {
            return $this->error(405, 'method_not_allowed', 'Dozvoljen je samo POST zahtev.');
        }
        if (!$this->authorized($headers) || !(bool) env_value('API_ENABLED', false)) {
            return $this->error(401, 'unauthorized', 'API autorizacija nije uspešna.');
        }
        $payload = $this->json($body);
        if ($payload === null) {
            return $this->error(400, 'invalid_json', 'Telo zahteva nije validan JSON.');
        }

        try {
            $claim = ($this->claims ?? new RewardClaimService())->notificationResult(
                $id,
                (string) ($payload['worker'] ?? ''),
                (string) ($payload['status'] ?? ''),
                isset($payload['provider_message_id']) ? (string) $payload['provider_message_id'] : null,
                isset($payload['error']) ? (string) $payload['error'] : null
            );
            return ['status' => 200, 'body' => ['status' => 'ok', 'notification_status' => $claim['notification_status']]];
        } catch (Throwable $error) {
            return $this->error(409, 'invalid_transition', $error->getMessage());
        }
    }

    /** @return array<string,mixed>|null */
    private function json(string $body): ?array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return is_array($payload) && !array_is_list($payload) ? $payload : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,string> $headers */
    private function authorized(array $headers): bool
    {
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $key = (string) env_value('N8N_API_KEY', env_value('PWRS_N8N_API_KEY', ''));
        return $key !== '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $match) === 1 && hash_equals($key, trim($match[1]));
    }

    /** @return array{status:int,body:array<string,mixed>} */
    private function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ['status' => 'error', 'error' => ['code' => $code, 'message' => $message]]];
    }
}
