# SenderKit for Laravel

Laravel integration for the [SenderKit PHP SDK](https://github.com/senderkit/senderkit-sdk-php).

## Install

```bash
composer require senderkit/senderkit-laravel
php artisan vendor:publish --tag=senderkit-config
```

Set `SENDERKIT_API_KEY` (and optionally `SENDERKIT_WEBHOOK_SECRET`) in `.env`.

## Usage

Prefer **SenderKit templates** over raw content: templates are versioned,
previewable, and editable in SenderKit without a deploy, and you get
per-template analytics. Raw sends (`sendRaw`, or anything routed through
Laravel Mail) lose all of that.

```php
use SenderKit\Laravel\Facades\SenderKit;
use SenderKit\Request\TemplateSend;

SenderKit::send(new TemplateSend(template: 'welcome', to: $user->email, vars: ['name' => $user->name]));

SenderKit::messages()->list();
SenderKit::templates()->list();
```

Or inject `SenderKit\Client` anywhere via the container.

## Notification channel (recommended)

The `senderkit` channel sends template-based notifications. Return a
`SenderKitMessage` from `toSenderKit()` — the recipient is resolved from the
notifiable's `senderkit` route, falling back to its `mail` route (so a `User`
with an `email` works out of the box):

```php
use Illuminate\Notifications\Notification;
use SenderKit\Laravel\Notifications\SenderKitMessage;

class OrderShipped extends Notification
{
    public function __construct(private Order $order) {}

    public function via(object $notifiable): array
    {
        return ['senderkit'];
    }

    public function toSenderKit(object $notifiable): SenderKitMessage
    {
        return SenderKitMessage::template('order-shipped')
            ->vars(['order_id' => $this->order->id, 'name' => $notifiable->name]);
    }
}
```

The channel is template-only by design. `SenderKitMessage` also supports
`version()`, `channel()`, `metadata()`, `scheduledAt()`, `cc()`, `bcc()`,
`replyTo()`, `attachments()`, `idempotencyKey()`, and `to()`.

### SMS, push, and web push

Set the message's channel and give the notifiable a `senderkit` route. The
route is either a string (used for any channel) or a map keyed by channel:

```php
use SenderKit\Enum\Channel;

// On the notifiable:
public function routeNotificationForSenderkit(): array
{
    return [
        'email' => $this->email,
        'sms' => $this->phone,
        'push' => $this->device_token,
        'web-push' => $this->web_push_subscription,
    ];
}

// In the notification:
public function toSenderKit(object $notifiable): SenderKitMessage
{
    return SenderKitMessage::template('otp')
        ->channel(Channel::Sms)
        ->vars(['code' => $this->code]);
}
```

Email messages fall back to the notifiable's `mail` route, so a plain `User`
works without any extra setup; SMS, push, and web push require a `senderkit`
route (or an explicit `->to()`) and are skipped when none is present.

`toSenderKit()` may also return a list of messages to notify on several
channels at once:

```php
public function toSenderKit(object $notifiable): array
{
    return [
        SenderKitMessage::template('order-shipped')->vars($vars), // email
        SenderKitMessage::template('order-shipped')->vars($vars)->channel(Channel::Push),
    ];
}
```

## Mail transport (for existing Mailables)

The `senderkit` mail transport lets an existing Mailable-based app deliver
through SenderKit without code changes. Add a mailer to `config/mail.php`:

```php
'mailers' => [
    'senderkit' => ['transport' => 'senderkit'],
],
```

and set `MAIL_MAILER=senderkit`. Existing `Mail::to(...)->send(...)` calls,
queued mail, and `mail`-channel notifications now go through SenderKit.

Note: Laravel renders Mailables to HTML locally, so the transport uses raw
sends — those messages bypass SenderKit templates (no versioning, preview, or
per-template analytics). It's the right tool for migrating; for new code,
prefer the notification channel or `TemplateSend` above. Emails with multiple
`to` recipients are sent as one API call per recipient.

## Webhooks

```php
use SenderKit\Laravel\Http\Middleware\VerifyWebhookSignature;

Route::post('/webhooks/senderkit', function (Request $request) {
    $event = $request->attributes->get('senderkit_event'); // SenderKit\Webhook\WebhookEvent
    // ...
})->middleware(VerifyWebhookSignature::class);
```

Invalid signatures get a 400; a missing `senderkit.webhook_secret` config yields a 500.

## License

[MIT](LICENSE)
