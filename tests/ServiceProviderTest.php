<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Tests;

use SenderKit\Client;

final class ServiceProviderTest extends TestCase
{
    public function test_container_resolves_client_singleton(): void
    {
        $client = $this->app->make(Client::class);
        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($client, $this->app->make(Client::class));
    }

    public function test_mode_comes_from_configured_key(): void
    {
        $this->assertSame('test', $this->app->make(Client::class)->mode);
    }

    public function test_config_defaults_merged(): void
    {
        $this->assertSame('https://api.senderkit.com', config('senderkit.base_url'));
        $this->assertSame(30000, config('senderkit.timeout_ms'));
        $this->assertSame(2, config('senderkit.max_retries'));
    }

    public function test_senderkit_alias_resolves(): void
    {
        $this->assertInstanceOf(Client::class, $this->app->make('senderkit'));
    }

    public function test_missing_api_key_throws_actionable_error(): void
    {
        config()->set('senderkit.api_key', '');
        $this->app->forgetInstance(\SenderKit\Client::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SENDERKIT_API_KEY/');
        $this->app->make(\SenderKit\Client::class);
    }
}
