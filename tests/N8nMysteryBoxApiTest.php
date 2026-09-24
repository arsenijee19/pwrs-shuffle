<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\MailDeliveryException;
use PlayWorld\MysteryBox\Services\MysteryBoxFulfillmentService;
use PlayWorld\MysteryBox\Services\N8nMysteryBoxApi;
use PlayWorld\MysteryBox\Support\Database;

final class N8nMysteryBoxApiTest extends TestCase
{
    public function testInvalidApiKeyIsRejected(): void
    {
        $this->configureApi();

        $response = $this->api()->handle('POST', $this->headers('wrong-key'), $this->json($this->payload()));

        $this->assertSame(401, $response['status']);
        $this->assertSame('invalid_api_key', $response['body']['error']['code']);
    }

    public function testDisabledApiIsRejectedBeforeProcessing(): void
    {
        $this->configureApi(false);

        $response = $this->api()->handle('POST', $this->headers(), $this->json($this->payload()));

        $this->assertSame(503, $response['status']);
        $this->assertSame('api_disabled', $response['body']['error']['code']);
    }

    public function testInvalidJsonIsRejected(): void
    {
        $this->configureApi();

        $response = $this->api()->handle('POST', $this->headers(), '{"order_id":');

        $this->assertSame(400, $response['status']);
        $this->assertSame('invalid_json', $response['body']['error']['code']);
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->configureApi();
        $payload = $this->payload(['email' => 'not-an-email']);

        $response = $this->api()->handle('POST', $this->headers(), $this->json($payload));

        $this->assertSame(422, $response['status']);
        $this->assertSame('invalid_email', $response['body']['error']['code']);
    }

    public function testZeroQuantityIsRejected(): void
    {
        $this->configureApi();
        $payload = $this->payload(['quantity' => 0]);

        $response = $this->api()->handle('POST', $this->headers(), $this->json($payload));

        $this->assertSame(422, $response['status']);
        $this->assertSame('invalid_quantity', $response['body']['error']['code']);
    }

    public function testQuantityAboveConfiguredLimitIsRejected(): void
    {
        $this->configureApi(true, 2);
        $payload = $this->payload(['quantity' => 3]);

        $response = $this->api()->handle('POST', $this->headers(), $this->json($payload));

        $this->assertSame(422, $response['status']);
        $this->assertSame('quantity_exceeds_limit', $response['body']['error']['code']);
    }

    public function testFirstSuccessfulFulfillmentQuantityOne(): void
    {
        $this->configureApi();
        $sent = [];
        $api = $this->api(static function (string $email, string $name, string $link) use (&$sent): void {
            $sent[] = $link;
        });

        $response = $api->handle('POST', $this->headers(), $this->json($this->payload(['quantity' => 1])));

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertSame('Completed', $response['body']['data']['status']);
        $this->assertSame(1, $response['body']['data']['sent_quantity']);
        $this->assertCount(1, $sent);
        $this->assertSame(1, (int) Database::pdo()->query('SELECT COUNT(*) FROM mystery_box_fulfillment_items')->fetchColumn());
    }

    public function testSuccessfulFulfillmentQuantityGreaterThanOne(): void
    {
        $this->configureApi();
        $sent = [];
        $api = $this->api(static function (string $email, string $name, string $link) use (&$sent): void {
            $sent[] = $link;
        });

        $response = $api->handle('POST', $this->headers(), $this->json($this->payload(['quantity' => 3])));

        $this->assertSame(200, $response['status']);
        $this->assertSame(3, $response['body']['data']['requested_quantity']);
        $this->assertSame(3, $response['body']['data']['sent_quantity']);
        $this->assertCount(3, $sent);
        $this->assertSame(3, (int) Database::pdo()->query('SELECT COUNT(*) FROM mystery_box_fulfillment_items')->fetchColumn());
    }

    public function testAdminCancellationRevokesActiveLinksAndStopsFutureReplay(): void
    {
        $this->configureApi();
        $sent = [];
        $api = $this->api(static function (string $email, string $name, string $link) use (&$sent): void {
            $sent[] = $link;
        });
        $body = $this->json($this->payload(['quantity' => 3]));

        $first = $api->handle('POST', $this->headers(), $body);
        $fulfillmentId = (int) Database::pdo()->query('SELECT id FROM mystery_box_fulfillments LIMIT 1')->fetchColumn();
        $cancelled = (new MysteryBoxFulfillmentService())->cancel($fulfillmentId);
        $second = $api->handle('POST', $this->headers(), $body);

        $this->assertSame('Cancelled', $cancelled['status']);
        $this->assertSame(3, $cancelled['revoked_quantity']);
        $this->assertSame(3, (int) Database::pdo()->query('SELECT COUNT(*) FROM tokens WHERE status = "Revoked"')->fetchColumn());
        $this->assertSame(409, $second['status']);
        $this->assertSame('fulfillment_cancelled', $second['body']['error']['code']);
        $this->assertSame('Cancelled', $second['body']['data']['status']);
        $this->assertCount(3, $sent);
        $this->assertSame('Revoked', (new \PlayWorld\MysteryBox\Services\TokenService())->findPlain($sent[0])['status']);
        $this->assertSame('Completed', $first['body']['data']['status']);
    }

    public function testIdenticalCompletedOrderIsAnIdempotentReplay(): void
    {
        $this->configureApi();
        $sent = 0;
        $api = $this->api(static function () use (&$sent): void {
            $sent++;
        });
        $body = $this->json($this->payload(['quantity' => 3]));

        $first = $api->handle('POST', $this->headers(), $body);
        $second = $api->handle('POST', $this->headers(), $body);

        $this->assertSame(200, $first['status']);
        $this->assertSame(200, $second['status']);
        $this->assertFalse($first['body']['data']['idempotent_replay']);
        $this->assertTrue($second['body']['data']['idempotent_replay']);
        $this->assertSame(3, $sent);
        $this->assertSame(3, (int) Database::pdo()->query('SELECT COUNT(*) FROM tokens')->fetchColumn());
    }

    public function testSameOrderWithDifferentEmailReturnsConflict(): void
    {
        $this->configureApi();
        $api = $this->api(static function (): void {
        });
        $api->handle('POST', $this->headers(), $this->json($this->payload(['quantity' => 1])));

        $response = $api->handle('POST', $this->headers(), $this->json($this->payload([
            'email' => 'drugi@example.com',
            'quantity' => 1,
        ])));

        $this->assertSame(409, $response['status']);
        $this->assertSame('fulfillment_conflict', $response['body']['error']['code']);
        $this->assertSame(1, (int) Database::pdo()->query('SELECT COUNT(*) FROM tokens')->fetchColumn());
    }

    public function testSameOrderWithDifferentQuantityReturnsConflict(): void
    {
        $this->configureApi();
        $api = $this->api(static function (): void {
        });
        $api->handle('POST', $this->headers(), $this->json($this->payload(['quantity' => 1])));

        $response = $api->handle('POST', $this->headers(), $this->json($this->payload(['quantity' => 2])));

        $this->assertSame(409, $response['status']);
        $this->assertSame('fulfillment_conflict', $response['body']['error']['code']);
        $this->assertSame(1, (int) Database::pdo()->query('SELECT COUNT(*) FROM mystery_box_fulfillment_items')->fetchColumn());
    }

    public function testKnownSmtpFailureCreatesPartialFulfillmentWithoutLeakingError(): void
    {
        $this->configureApi();
        $api = $this->api(static function (): void {
            throw new \RuntimeException('SMTP secret should never be returned');
        });

        $response = $api->handle('POST', $this->headers(), $this->json($this->payload(['quantity' => 1])));

        $this->assertSame(502, $response['status']);
        $this->assertSame('delivery_failed', $response['body']['error']['code']);
        $this->assertStringNotContainsString('SMTP secret', json_encode($response['body'], JSON_THROW_ON_ERROR));
        $this->assertSame('Partial', $response['body']['data']['status']);
        $this->assertSame('Failed', $response['body']['data']['items'][0]['status']);
    }

    public function testPartialFulfillmentRetrySendsOnlyFailedItem(): void
    {
        $this->configureApi();
        $attempts = [];
        $successfulLinks = [];
        $api = $this->api(static function (string $email, string $name, string $link) use (&$attempts, &$successfulLinks): void {
            $attempts[] = $link;
            if (count($attempts) === 2) {
                throw new \RuntimeException('temporary SMTP failure');
            }
            $successfulLinks[] = $link;
        });
        $body = $this->json($this->payload(['quantity' => 3]));

        $first = $api->handle('POST', $this->headers(), $body);
        $second = $api->handle('POST', $this->headers(), $body);

        $this->assertSame(502, $first['status']);
        $this->assertSame('Partial', $first['body']['data']['status']);
        $this->assertSame(2, $first['body']['data']['sent_quantity']);
        $this->assertSame(200, $second['status']);
        $this->assertSame('Completed', $second['body']['data']['status']);
        $this->assertSame(3, $second['body']['data']['sent_quantity']);
        $this->assertCount(4, $attempts);
        $this->assertCount(3, array_unique($successfulLinks));
        $this->assertSame(3, (int) Database::pdo()->query('SELECT COUNT(*) FROM mystery_box_fulfillment_items WHERE status = "Sent"')->fetchColumn());
    }

    public function testSendingUnknownOutcomeMovesToManualReviewAndNeverRetries(): void
    {
        $this->configureApi();
        $attempts = 0;
        $api = $this->api(static function () use (&$attempts): void {
            $attempts++;
            throw new MailDeliveryException('unknown', true, 'smtp_unknown');
        });
        $body = $this->json($this->payload(['quantity' => 1]));

        $first = $api->handle('POST', $this->headers(), $body);
        $second = $api->handle('POST', $this->headers(), $body);

        $this->assertSame(409, $first['status']);
        $this->assertSame('needs_manual_review', $first['body']['error']['code']);
        $this->assertSame(409, $second['status']);
        $this->assertSame('needs_manual_review', $second['body']['error']['code']);
        $this->assertSame(1, $attempts);
        $this->assertSame(1, (int) Database::pdo()->query('SELECT COUNT(*) FROM tokens')->fetchColumn());
    }

    public function testAdminSingleLinkDeliveryUsesSharedService(): void
    {
        $sent = [];
        $service = new MysteryBoxFulfillmentService(static function (string $email, string $name, string $link) use (&$sent): void {
            $sent[] = $link;
        });

        $created = $service->createAndSend('Admin@Example.com', 'Admin kupac');

        $this->assertCount(1, $sent);
        $this->assertSame($created['link'], $sent[0]);
        $this->assertNotNull((new \PlayWorld\MysteryBox\Services\TokenService())->findPlain($created['token']));
        $this->assertSame('Sent', Database::pdo()->query('SELECT status FROM email_deliveries LIMIT 1')->fetchColumn());
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'order_id' => 'PW-TEST-1001',
            'email' => 'test@example.com',
            'name' => 'Test Kupac',
            'quantity' => 3,
        ], $overrides);
    }

    private function configureApi(bool $enabled = true, int $maxQuantity = 20): void
    {
        $_ENV['API_ENABLED'] = $enabled ? 'true' : 'false';
        $_ENV['N8N_API_KEY'] = 'test-api-key';
        $_ENV['N8N_API_MAX_QUANTITY'] = (string) $maxQuantity;
    }

    /** @param callable(string, string, string): void|null $sender */
    private function api(?callable $sender = null): N8nMysteryBoxApi
    {
        return new N8nMysteryBoxApi(new MysteryBoxFulfillmentService($sender));
    }

    /** @return array<string, string> */
    private function headers(string $key = 'test-api-key'): array
    {
        return [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type' => 'application/json',
        ];
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
