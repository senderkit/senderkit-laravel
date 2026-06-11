<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Tests;

use GuzzleHttp\Psr7\Response;
use SenderKit\Client;
use SenderKit\Laravel\Facades\SenderKit;
use SenderKit\Laravel\Tests\Support\FakeHttpClient;
use SenderKit\Request\TemplateSend;
use SenderKit\Resource\Messages;
use SenderKit\Resource\Templates;

final class FacadeTest extends TestCase
{
    public function test_facade_root_is_the_container_client(): void
    {
        $this->assertSame($this->app->make(Client::class), SenderKit::getFacadeRoot());
    }

    public function test_facade_send_round_trip_via_container(): void
    {
        $fake = new FakeHttpClient([
            new Response(202, [], '{"id":"msg_1","status":"queued","livemode":false}'),
        ]);
        $this->app->instance(Client::class, new Client(apiKey: 'sk_test_pkg', httpClient: $fake));

        $result = SenderKit::send(new TemplateSend(template: 'welcome', to: 'a@b.com'));

        $this->assertSame('msg_1', $result->id);
        $this->assertSame('queued', $result->status);
        $this->assertSame(
            'https://api.senderkit.com/v1/send',
            (string) $fake->requests[0]->getUri(),
        );
        $this->assertSame('Bearer sk_test_pkg', $fake->requests[0]->getHeaderLine('Authorization'));
    }

    public function test_facade_resource_accessors(): void
    {
        $this->assertInstanceOf(Messages::class, SenderKit::messages());
        $this->assertInstanceOf(Templates::class, SenderKit::templates());
    }
}
