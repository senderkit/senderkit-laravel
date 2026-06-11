<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SenderKit\Laravel\SenderKitServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @param \Illuminate\Foundation\Application $app */
    protected function getPackageProviders($app): array
    {
        return [SenderKitServiceProvider::class];
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('senderkit.api_key', 'sk_test_pkg');
        $app['config']->set('senderkit.webhook_secret', 'whsec_pkg');
    }
}
