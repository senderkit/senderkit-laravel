<?php

declare(strict_types=1);

namespace SenderKit\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use SenderKit\Client;
use SenderKit\Laravel\Mail\SenderKitTransport;
use SenderKit\Laravel\Notifications\SenderKitChannel;
use SenderKit\Webhook\WebhookVerifier;

final class SenderKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/senderkit.php', 'senderkit');

        $this->app->singleton(Client::class, static function (Application $app): Client {
            /** @var Repository $config */
            $config = $app->make('config');

            $apiKey = self::stringValue($config, 'senderkit.api_key', '');
            if ($apiKey === '') {
                throw new \RuntimeException(
                    'SenderKit API key is not configured. Set SENDERKIT_API_KEY in your .env '
                    . '(config key "senderkit.api_key").',
                );
            }

            return new Client(
                apiKey: $apiKey,
                baseUrl: self::stringValue($config, 'senderkit.base_url', 'https://api.senderkit.com'),
                timeoutMs: self::intValue($config, 'senderkit.timeout_ms', 30000),
                maxRetries: self::intValue($config, 'senderkit.max_retries', 2),
            );
        });
        $this->app->alias(Client::class, 'senderkit');

        $this->app->singleton(WebhookVerifier::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/senderkit.php' => config_path('senderkit.php'),
        ], 'senderkit-config');

        $this->app->afterResolving(MailManager::class, function (MailManager $manager): void {
            $manager->extend('senderkit', fn (): SenderKitTransport => new SenderKitTransport($this->client()));
        });

        $this->app->afterResolving(ChannelManager::class, function (ChannelManager $manager): void {
            $manager->extend('senderkit', fn (): SenderKitChannel => new SenderKitChannel($this->client()));
        });
    }

    private function client(): Client
    {
        /** @var Client */
        return $this->app->make(Client::class);
    }

    private static function stringValue(Repository $config, string $key, string $default): string
    {
        $value = $config->get($key, $default);

        return is_string($value) ? $value : $default;
    }

    private static function intValue(Repository $config, string $key, int $default): int
    {
        $value = $config->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
