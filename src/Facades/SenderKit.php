<?php

declare(strict_types=1);

namespace SenderKit\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use SenderKit\Client;
use SenderKit\Resource\Messages;
use SenderKit\Resource\Templates;

/**
 * @method static \SenderKit\Response\SendResult send(\SenderKit\Request\TemplateSend $request)
 * @method static \SenderKit\Response\SendResult sendRaw(\SenderKit\Request\RawSend $request)
 * @method static array sendBatch(array $requests, ?\SenderKit\Request\BatchOptions $options = null)
 * @method static \SenderKit\Response\Context context()
 *
 * @see Client
 */
final class SenderKit extends Facade
{
    public static function messages(): Messages
    {
        /** @var Client $client */
        $client = static::getFacadeRoot();

        return $client->messages;
    }

    public static function templates(): Templates
    {
        /** @var Client $client */
        $client = static::getFacadeRoot();

        return $client->templates;
    }

    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
