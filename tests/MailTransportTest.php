<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Tests;

use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use SenderKit\Client;
use SenderKit\Laravel\Mail\SenderKitTransport;
use SenderKit\Laravel\Tests\Support\FakeHttpClient;

final class MailTransportTest extends TestCase
{
    private FakeHttpClient $fake;

    /** @param \Illuminate\Foundation\Application $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mail.default', 'senderkit');
        $app['config']->set('mail.mailers.senderkit', ['transport' => 'senderkit']);
        $app['config']->set('mail.from', ['address' => 'noreply@acme.test', 'name' => 'Acme']);
    }

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

    public function test_mailer_resolves_the_senderkit_transport(): void
    {
        $transport = Mail::mailer('senderkit')->getSymfonyTransport();

        $this->assertInstanceOf(SenderKitTransport::class, $transport);
        $this->assertSame('senderkit', (string) $transport);
    }

    public function test_html_email_is_sent_as_raw_email_send(): void
    {
        $this->bindFakeClient();

        Mail::html('<p>Hello</p>', static function (Message $message): void {
            $message->to('to@example.com')->subject('Greetings');
        });

        $this->assertCount(1, $this->fake->requests);
        $this->assertSame(
            'https://api.senderkit.com/v1/send',
            (string) $this->fake->requests[0]->getUri(),
        );

        $body = $this->requestBody(0);
        $this->assertSame('email', $body['channel']);
        $this->assertSame('to@example.com', $body['to']);
        $this->assertSame('noreply@acme.test', $body['from']);
        $this->assertSame('Greetings', $body['content']['subject']);
        $this->assertSame('<p>Hello</p>', $body['content']['html']);
    }

    public function test_text_only_email_gets_escaped_html_fallback(): void
    {
        $this->bindFakeClient();

        Mail::raw("Hi <you>\nBye", static function (Message $message): void {
            $message->to('to@example.com')->subject('Plain');
        });

        $body = $this->requestBody(0);
        $this->assertSame("Hi <you>\nBye", $body['content']['text']);
        $this->assertSame("Hi &lt;you&gt;<br />\nBye", $body['content']['html']);
    }

    public function test_each_to_recipient_gets_its_own_api_call(): void
    {
        $this->bindFakeClient(2);

        Mail::html('<p>Hi</p>', static function (Message $message): void {
            $message->to(['a@example.com', 'b@example.com'])->subject('Multi');
        });

        $this->assertCount(2, $this->fake->requests);
        $this->assertSame('a@example.com', $this->requestBody(0)['to']);
        $this->assertSame('b@example.com', $this->requestBody(1)['to']);
    }

    public function test_cc_bcc_and_reply_to_are_mapped(): void
    {
        $this->bindFakeClient();

        Mail::html('<p>Hi</p>', static function (Message $message): void {
            $message->to('to@example.com')
                ->cc('cc@example.com')
                ->bcc('bcc@example.com')
                ->replyTo('reply@example.com')
                ->subject('Copies');
        });

        $body = $this->requestBody(0);
        $this->assertSame(['cc@example.com'], $body['content']['cc']);
        $this->assertSame(['bcc@example.com'], $body['content']['bcc']);
        $this->assertSame('reply@example.com', $body['content']['replyTo']);
    }

    public function test_attachments_are_mapped(): void
    {
        $this->bindFakeClient();

        Mail::html('<p>Hi</p>', static function (Message $message): void {
            $message->to('to@example.com')->subject('Files');
            $message->attachData('PDFDATA', 'invoice.pdf', ['mime' => 'application/pdf']);
        });

        $attachment = $this->requestBody(0)['content']['attachments'][0];
        $this->assertSame('invoice.pdf', $attachment['filename']);
        $this->assertSame('application/pdf', $attachment['contentType']);
        $this->assertSame(base64_encode('PDFDATA'), $attachment['content']);
    }
}
