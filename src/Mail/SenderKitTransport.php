<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Mail;

use SenderKit\Client;
use SenderKit\Request\Attachment;
use SenderKit\Request\EmailContent;
use SenderKit\Request\RawSend;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Routes Laravel Mail through SenderKit's raw-send API.
 *
 * Because Laravel renders Mailables to HTML locally, every send through this
 * transport bypasses SenderKit templates — you lose versioning, preview, and
 * per-template analytics. Use it to migrate an existing Mailable-based app
 * without code changes; for new code, prefer SenderKit templates via the
 * notification channel or `SenderKit::send(new TemplateSend(...))`.
 *
 * Emails with multiple To recipients are fanned out as one API call per
 * recipient, since the SenderKit API addresses a single recipient per send.
 */
final class SenderKitTransport extends AbstractTransport
{
    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'senderkit';
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (!$original instanceof Message) {
            throw new \LogicException('SenderKit transport requires a structured MIME message.');
        }
        $email = MessageConverter::toEmail($original);

        $content = new EmailContent(
            subject: (string) $email->getSubject(),
            html: $this->htmlBody($email),
            text: $this->stringBody($email->getTextBody()),
            cc: $this->addressList($email->getCc()),
            bcc: $this->addressList($email->getBcc()),
            replyTo: ($email->getReplyTo()[0] ?? null)?->getAddress(),
            attachments: $this->attachments($email),
        );

        $from = ($email->getFrom()[0] ?? null)?->getAddress();

        foreach ($email->getTo() as $recipient) {
            $this->client->sendRaw(new RawSend(
                to: $recipient->getAddress(),
                content: $content,
                from: $from,
            ));
        }
    }

    /** The API requires `html`, so text-only emails get an escaped fallback. */
    private function htmlBody(Email $email): string
    {
        $html = $this->stringBody($email->getHtmlBody());
        if ($html !== null) {
            return $html;
        }

        return nl2br(htmlspecialchars((string) $this->stringBody($email->getTextBody())));
    }

    /** @param resource|string|null $body */
    private function stringBody($body): ?string
    {
        if (\is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return \is_string($body) ? $body : null;
    }

    /**
     * @param list<Address> $addresses
     * @return list<string>|null
     */
    private function addressList(array $addresses): ?array
    {
        if ($addresses === []) {
            return null;
        }

        return array_map(static fn (Address $a): string => $a->getAddress(), $addresses);
    }

    /** @return list<Attachment>|null */
    private function attachments(Email $email): ?array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $inline = $part->getDisposition() === 'inline';
            $attachments[] = new Attachment(
                filename: $part->getFilename() ?? 'attachment',
                contentType: $part->getMediaType() . '/' . $part->getMediaSubtype(),
                content: base64_encode($part->getBody()),
                inline: $inline ?: null,
                contentId: $inline && $part->hasContentId() ? $part->getContentId() : null,
            );
        }

        return $attachments === [] ? null : $attachments;
    }
}
