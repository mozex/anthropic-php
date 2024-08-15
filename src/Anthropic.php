<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Factory;

final class Anthropic
{
    /**
     * Creates a new Anthropic Client with the given API token.
     */
    public static function client(string $apiKey): Client
    {
        return self::factory()
            ->withApiKey($apiKey)
            ->withHttpHeader('anthropic-version', '2023-06-01')
            ->make();
    }

    /**
     * Creates a new factory instance to configure a custom Anthropic Client
     */
    public static function factory(): Factory
    {
        return new Factory;
    }
}
