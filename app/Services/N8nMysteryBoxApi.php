<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use Throwable;

final class N8nMysteryBoxApi
{
    public function __construct(private readonly ?MysteryBoxFulfillmentService $fulfillments = null)
    {
    }

    /**
     * Handle one JSON API request without emitting output. This keeps validation and
     * response contracts directly testable while the front controller handles headers.
     *
     * @param array<string, string> $headers
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handle(string $method, array $headers, string $body): array
    {
        if (strtoupper($method) !== 'POST') {
            return $this->error(405, 'method_not_allowed', 'Dozvoljen je samo POST zahtev.');
        }

        $api = (new SettingsService())->apiConfig();
        if (!$this->authorized($headers, $api['key'])) {
            return $this->error(401, 'invalid_api_key', 'API autorizacija nije uspešna.');
        }

        if (!$api['enabled']) {
            return $this->error(503, 'api_disabled', 'Mystery Box API trenutno nije uključen.');
        }

        if (!$this->isJsonContentType($headers)) {
            return $this->error(415, 'unsupported_media_type', 'Content-Type mora biti application/json.');
        }

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->error(400, 'invalid_json', 'Telo zahteva nije validan JSON.');
        }

        if (!is_array($payload) || array_is_list($payload)) {
            return $this->error(400, 'invalid_json', 'JSON telo mora biti objekat.');
        }

        $orderId = $this->stringValue($payload['order_id'] ?? null);
        $email = $this->stringValue($payload['email'] ?? null);
        $name = $this->stringValue($payload['name'] ?? '');
        $quantity = $this->integerValue($payload['quantity'] ?? null);
        $maxQuantity = $api['max_quantity'];

        if ($orderId === null) {
            return $this->error(422, 'invalid_order_id', 'Polje order_id je obavezno.');
        }
        if ($email === null) {
            return $this->error(422, 'invalid_email', 'Polje email je obavezno.');
        }
        if ($quantity === null) {
            return $this->error(422, 'invalid_quantity', 'Polje quantity mora biti ceo broj.');
        }

        try {
            $result = ($this->fulfillments ?? new MysteryBoxFulfillmentService())->fulfill(
                $orderId,
                $email,
                $name ?? '',
                $quantity,
                $maxQuantity
            );
        } catch (FulfillmentException $error) {
            return $this->error($error->httpStatus, $error->errorCode, $error->getMessage());
        } catch (Throwable) {
            return $this->error(500, 'internal_error', 'Došlo je do interne greške pri obradi fulfillment-a.');
        }

        $status = $result['status'];
        if ($status === 'NeedsReview') {
            return [
                'status' => 409,
                'body' => [
                    'success' => false,
                    'error' => [
                        'code' => 'needs_manual_review',
                        'message' => 'Isporuka je zaustavljena radi ručne provere; email se neće automatski ponoviti.',
                    ],
                    'data' => $this->publicResult($result),
                ],
            ];
        }

        if ($status === 'Partial' || $status === 'Processing') {
            return [
                'status' => 502,
                'body' => [
                    'success' => false,
                    'error' => [
                        'code' => 'delivery_failed',
                        'message' => 'Nisu svi Mystery Box linkovi uspešno poslati. Bezbedno je ponoviti isti zahtev.',
                    ],
                    'data' => $this->publicResult($result),
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $this->publicResult($result),
            ],
        ];
    }

    /** @param array<string, string> $headers */
    private function authorized(array $headers, string $expected): bool
    {
        $authorization = '';
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $authorization = trim($value);
                break;
            }
        }

        if ($expected === '' || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return false;
        }

        return hash_equals($expected, trim($matches[1]));
    }

    /** @param array<string, string> $headers */
    private function isJsonContentType(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                return (bool) preg_match('/^application\/json(?:\s*;|\s*$)/i', trim($value));
            }
        }

        return false;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) ? trim($value) : null;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            return (int) trim($value);
        }

        return null;
    }

    /** @param array<string, mixed> $result */
    private function publicResult(array $result): array
    {
        return [
            'order_id' => $result['order_id'],
            'status' => $result['status'],
            'requested_quantity' => $result['requested_quantity'],
            'sent_quantity' => $result['sent_quantity'],
            'idempotent_replay' => $result['idempotent_replay'],
            'resumed' => $result['resumed'],
            'items' => $result['items'],
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function error(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
        ];
    }
}
