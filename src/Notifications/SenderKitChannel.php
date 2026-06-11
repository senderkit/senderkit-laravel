<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Notifications;

use Illuminate\Notifications\Notification;
use SenderKit\Client;
use SenderKit\Enum\Channel;

/**
 * Notification channel that sends SenderKit template messages on any
 * SenderKit channel: email, SMS, push, or web push.
 *
 * Notifications must implement `toSenderKit($notifiable)` returning a
 * SenderKitMessage or a list of them (one send each). The recipient comes
 * from the notifiable's `senderkit` route — either a string used for any
 * channel, or a map keyed by channel value, e.g.
 * `['email' => $email, 'sms' => $phone, 'push' => $token]`. Email messages
 * additionally fall back to the `mail` route; other channels never do.
 * An explicit `->to()` on the message wins over routes.
 */
final class SenderKitChannel
{
    public function __construct(private readonly Client $client)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSenderKit')) {
            throw new \LogicException(sprintf(
                '%s must define a toSenderKit($notifiable): SenderKitMessage method to use the senderkit channel.',
                $notification::class,
            ));
        }

        $messages = $notification->toSenderKit($notifiable);
        foreach (\is_array($messages) ? $messages : [$messages] as $message) {
            if (!$message instanceof SenderKitMessage) {
                throw new \LogicException(sprintf(
                    '%s::toSenderKit() must return a %s instance or a list of them.',
                    $notification::class,
                    SenderKitMessage::class,
                ));
            }

            $to = $message->recipient()
                ?? $this->routeFor($notifiable, $notification, $message->messageChannel());
            if ($to === null) {
                continue;
            }

            $this->client->send($message->toTemplateSend($to));
        }
    }

    private function routeFor(object $notifiable, Notification $notification, ?Channel $channel): ?string
    {
        if (!method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor('senderkit', $notification);
        if (\is_array($route)) {
            $route = $route[($channel ?? Channel::Email)->value] ?? null;
        }

        if ($route === null && ($channel === null || $channel === Channel::Email)) {
            $route = $this->mailRoute($notifiable, $notification);
        }

        return \is_string($route) && $route !== '' ? $route : null;
    }

    private function mailRoute(object $notifiable, Notification $notification): mixed
    {
        if (!method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor('mail', $notification);

        // Mail routes may be [address => name] or a list of addresses.
        if (\is_array($route)) {
            $key = array_key_first($route);
            $route = \is_string($key) ? $key : ($key === null ? null : $route[$key]);
        }

        return $route;
    }
}
