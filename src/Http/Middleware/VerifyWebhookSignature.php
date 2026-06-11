<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use SenderKit\Exception\SignatureVerificationException;
use SenderKit\Webhook\WebhookVerifier;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    public function __construct(
        private readonly WebhookVerifier $verifier,
        private readonly Repository $config,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->config->get('senderkit.webhook_secret');
        if (!is_string($secret) || $secret === '') {
            abort(500, 'SenderKit webhook secret is not configured.');
        }

        $sigHeader = $request->header('X-SenderKit-Signature', '');
        $eventType = $request->header('X-SenderKit-Event');
        $deliveryId = $request->header('X-SenderKit-Delivery');

        try {
            $event = $this->verifier->verify(
                rawBody: (string) $request->getContent(),
                signatureHeader: is_string($sigHeader) ? $sigHeader : '',
                secret: $secret,
                eventType: is_string($eventType) ? $eventType : null,
                deliveryId: is_string($deliveryId) ? $deliveryId : null,
            );
        } catch (SignatureVerificationException) {
            abort(400, 'Invalid SenderKit webhook signature.');
        }

        $request->attributes->set('senderkit_event', $event);

        return $next($request);
    }
}
