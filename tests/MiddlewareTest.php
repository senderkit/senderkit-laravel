<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Tests;

use Illuminate\Http\Request;
use SenderKit\Laravel\Http\Middleware\VerifyWebhookSignature;
use SenderKit\Webhook\WebhookEvent;

final class MiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('/sk-webhook', static function (Request $request) {
            $event = $request->attributes->get('senderkit_event');
            assert($event instanceof WebhookEvent);

            return response()->json(['type' => $event->type, 'id' => $event->payload['id'] ?? null]);
        })->middleware(VerifyWebhookSignature::class);
    }

    private function signedHeaders(string $body, ?int $t = null): array
    {
        $secret = config('senderkit.webhook_secret');
        $secret = is_string($secret) ? $secret : '';
        $t ??= time();
        $mac = hash_hmac('sha256', $t . '.' . $body, $secret);

        return [
            'HTTP_X_SENDERKIT_SIGNATURE' => "t={$t},v1={$mac}",
            'HTTP_X_SENDERKIT_EVENT' => 'message.delivered',
            'HTTP_X_SENDERKIT_DELIVERY' => 'del_1',
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    public function test_valid_signature_passes_and_exposes_event(): void
    {
        $body = '{"id":"msg_1"}';
        $response = $this->call('POST', '/sk-webhook', [], [], [], $this->signedHeaders($body), $body);

        $response->assertOk();
        $response->assertJson(['type' => 'message.delivered', 'id' => 'msg_1']);
    }

    public function test_invalid_signature_rejected_with_400(): void
    {
        $body = '{"id":"msg_1"}';
        $headers = $this->signedHeaders('{"id":"tampered"}');
        $response = $this->call('POST', '/sk-webhook', [], [], [], $headers, $body);

        $response->assertStatus(400);
    }

    public function test_missing_secret_yields_500(): void
    {
        config()->set('senderkit.webhook_secret', null);
        $body = '{"id":"msg_1"}';
        $response = $this->call('POST', '/sk-webhook', [], [], [], $this->signedHeaders($body), $body);

        $response->assertStatus(500);
    }
}
