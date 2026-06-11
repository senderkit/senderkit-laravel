# SenderKit for Laravel

Laravel integration for the [SenderKit PHP SDK](https://github.com/senderkit/senderkit-sdk-php).

## Install

```bash
composer require senderkit/senderkit-laravel
php artisan vendor:publish --tag=senderkit-config
```

Set `SENDERKIT_API_KEY` (and optionally `SENDERKIT_WEBHOOK_SECRET`) in `.env`.

## Usage

```php
use SenderKit\Laravel\Facades\SenderKit;
use SenderKit\Request\TemplateSend;

SenderKit::send(new TemplateSend(template: 'welcome', to: $user->email, vars: ['name' => $user->name]));

SenderKit::messages()->list();
SenderKit::templates()->list();
```

Or inject `SenderKit\Client` anywhere via the container.

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
