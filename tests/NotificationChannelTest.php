<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Tests;

use GuzzleHttp\Psr7\Response;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use SenderKit\Client;
use SenderKit\Enum\Channel;
use SenderKit\Laravel\Notifications\SenderKitMessage;
use SenderKit\Laravel\Tests\Support\FakeHttpClient;

final class NotificationChannelTest extends TestCase
{
    private FakeHttpClient $fake;

    private function bindFakeClient(int $responses = 1): void
    {
        $queue = [];
        for ($i = 0; $i < $responses; $i++) {
            $queue[] = new Response(202, [], '{"id":"msg_' . $i . '","status":"queued","livemode":false}');
        }
        $this->fake = new FakeHttpClient($queue);
        $this->app->instance(Client::class, new Client(apiKey: 'sk_test_pkg', httpClient: $this->fake));
    }

    /** @return array<string,mixed> */
    private function requestBody(int $index): array
    {
        /** @var array<string,mixed> */
        return json_decode((string) $this->fake->requests[$index]->getBody(), true);
    }

    public function test_notification_sends_template_send_to_the_senderkit_route(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('senderkit', 'user@example.com')
            ->notifyNow(new WelcomeNotification());

        $this->assertCount(1, $this->fake->requests);
        $body = $this->requestBody(0);
        $this->assertSame('welcome', $body['template']);
        $this->assertSame('user@example.com', $body['to']);
        $this->assertSame(['name' => 'Ada'], $body['vars']);
        $this->assertArrayNotHasKey('content', $body);
    }

    public function test_route_falls_back_to_the_mail_route(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('mail', ['fallback@example.com' => 'Fallback'])
            ->notifyNow(new WelcomeNotification());

        $this->assertSame('fallback@example.com', $this->requestBody(0)['to']);
    }

    public function test_explicit_to_on_the_message_wins_over_routes(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('senderkit', 'route@example.com')
            ->notifyNow(new OverrideToNotification());

        $this->assertSame('override@example.com', $this->requestBody(0)['to']);
    }

    public function test_message_options_are_mapped(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('senderkit', 'user@example.com')
            ->notifyNow(new KitchenSinkNotification());

        $body = $this->requestBody(0);
        $this->assertSame('order-shipped', $body['template']);
        $this->assertSame(2, $body['version']);
        $this->assertSame(['order_id' => 'ord_1'], $body['metadata']);
        $this->assertSame(['cc@example.com'], $body['cc']);
        $this->assertSame('reply@example.com', $body['replyTo']);
        $this->assertSame(
            'idem-123',
            $this->fake->requests[0]->getHeaderLine('Idempotency-Key'),
        );
    }

    public function test_notification_without_to_sender_kit_method_throws(): void
    {
        $this->bindFakeClient();

        $this->expectException(\LogicException::class);

        NotificationFacade::route('senderkit', 'user@example.com')
            ->notifyNow(new MethodlessNotification());
    }

    public function test_notifiable_without_any_route_is_skipped(): void
    {
        $this->bindFakeClient();

        (new AnonymousNotifiable())->notifyNow(new WelcomeNotification());

        $this->assertCount(0, $this->fake->requests);
    }

    public function test_sms_message_picks_the_sms_entry_from_a_channel_map_route(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('senderkit', ['email' => 'user@example.com', 'sms' => '+15550100'])
            ->notifyNow(new SmsNotification());

        $body = $this->requestBody(0);
        $this->assertSame('+15550100', $body['to']);
        $this->assertSame('sms', $body['channel']);
    }

    public function test_channel_map_route_defaults_to_email_when_message_has_no_channel(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('senderkit', ['email' => 'user@example.com', 'sms' => '+15550100'])
            ->notifyNow(new WelcomeNotification());

        $this->assertSame('user@example.com', $this->requestBody(0)['to']);
    }

    public function test_push_and_web_push_channels_route_from_the_channel_map(): void
    {
        $this->bindFakeClient(2);

        NotificationFacade::route('senderkit', ['push' => 'tok_device', 'web-push' => 'sub_endpoint'])
            ->notifyNow(new PushAndWebPushNotification());

        $this->assertSame('tok_device', $this->requestBody(0)['to']);
        $this->assertSame('push', $this->requestBody(0)['channel']);
        $this->assertSame('sub_endpoint', $this->requestBody(1)['to']);
        $this->assertSame('web-push', $this->requestBody(1)['channel']);
    }

    public function test_non_email_message_never_falls_back_to_the_mail_route(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('mail', 'user@example.com')
            ->notifyNow(new SmsNotification());

        $this->assertCount(0, $this->fake->requests);
    }

    public function test_string_senderkit_route_is_used_for_any_channel(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('senderkit', '+15550100')
            ->notifyNow(new SmsNotification());

        $this->assertSame('+15550100', $this->requestBody(0)['to']);
    }

    public function test_channel_map_without_an_entry_for_the_message_channel_is_skipped(): void
    {
        $this->bindFakeClient();

        NotificationFacade::route('senderkit', ['email' => 'user@example.com'])
            ->notifyNow(new SmsNotification());

        $this->assertCount(0, $this->fake->requests);
    }
}

final class WelcomeNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['senderkit'];
    }

    public function toSenderKit(object $notifiable): SenderKitMessage
    {
        return SenderKitMessage::template('welcome')->vars(['name' => 'Ada']);
    }
}

final class OverrideToNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['senderkit'];
    }

    public function toSenderKit(object $notifiable): SenderKitMessage
    {
        return SenderKitMessage::template('welcome')->to('override@example.com');
    }
}

final class KitchenSinkNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['senderkit'];
    }

    public function toSenderKit(object $notifiable): SenderKitMessage
    {
        return SenderKitMessage::template('order-shipped')
            ->version(2)
            ->metadata(['order_id' => 'ord_1'])
            ->cc(['cc@example.com'])
            ->replyTo('reply@example.com')
            ->from('hello@acme.com')
            ->fromName('Acme Support')
            ->idempotencyKey('idem-123');
    }
}

final class MethodlessNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['senderkit'];
    }
}

final class SmsNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['senderkit'];
    }

    public function toSenderKit(object $notifiable): SenderKitMessage
    {
        return SenderKitMessage::template('otp')
            ->channel(Channel::Sms)
            ->vars(['code' => '123456']);
    }
}

final class PushAndWebPushNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['senderkit'];
    }

    /** @return list<SenderKitMessage> */
    public function toSenderKit(object $notifiable): array
    {
        return [
            SenderKitMessage::template('order-update')->channel(Channel::Push),
            SenderKitMessage::template('order-update')->channel(Channel::WebPush),
        ];
    }
}
