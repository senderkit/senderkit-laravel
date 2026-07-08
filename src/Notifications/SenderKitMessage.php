<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Notifications;

use SenderKit\Enum\Channel;
use SenderKit\Request\Attachment;
use SenderKit\Request\TemplateSend;

/**
 * Template-based message for the `senderkit` notification channel.
 *
 * The channel is deliberately template-only: content lives in SenderKit
 * (versioned, previewable, editable without a deploy) and the notification
 * passes only the template slug and variables.
 */
final class SenderKitMessage
{
    private ?string $to = null;

    /** @var array<string,mixed>|null */
    private ?array $vars = null;

    private ?int $version = null;

    private ?Channel $channel = null;

    /** @var array<string,string|int|bool|float>|null */
    private ?array $metadata = null;

    private \DateTimeInterface|string|null $scheduledAt = null;

    /** @var list<string>|null */
    private ?array $cc = null;

    /** @var list<string>|null */
    private ?array $bcc = null;

    private ?string $replyTo = null;

    private ?string $from = null;

    private ?string $fromName = null;

    /** @var list<Attachment>|null */
    private ?array $attachments = null;

    private ?string $idempotencyKey = null;

    private function __construct(private readonly string $template)
    {
    }

    public static function template(string $template): self
    {
        return new self($template);
    }

    /** Overrides the recipient resolved from the notifiable's routes. */
    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    /** @param array<string,mixed> $vars */
    public function vars(array $vars): self
    {
        $this->vars = $vars;

        return $this;
    }

    public function version(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function channel(Channel $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    /** @param array<string,string|int|bool|float> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function scheduledAt(\DateTimeInterface|string $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /** @param list<string> $cc */
    public function cc(array $cc): self
    {
        $this->cc = $cc;

        return $this;
    }

    /** @param list<string> $bcc */
    public function bcc(array $bcc): self
    {
        $this->bcc = $bcc;

        return $this;
    }

    public function replyTo(string $replyTo): self
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /** Email-only From address override (bare address; put the display name in fromName()). */
    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    /** Email-only From display name override, rendered as `Name <address>`. */
    public function fromName(string $fromName): self
    {
        $this->fromName = $fromName;

        return $this;
    }

    /** @param list<Attachment> $attachments */
    public function attachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    public function idempotencyKey(string $idempotencyKey): self
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function recipient(): ?string
    {
        return $this->to;
    }

    public function messageChannel(): ?Channel
    {
        return $this->channel;
    }

    public function toTemplateSend(string $to): TemplateSend
    {
        return new TemplateSend(
            template: $this->template,
            to: $to,
            vars: $this->vars,
            version: $this->version,
            channel: $this->channel,
            metadata: $this->metadata,
            scheduledAt: $this->scheduledAt,
            cc: $this->cc,
            bcc: $this->bcc,
            replyTo: $this->replyTo,
            attachments: $this->attachments,
            from: $this->from,
            fromName: $this->fromName,
            idempotencyKey: $this->idempotencyKey,
        );
    }
}
