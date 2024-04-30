<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Factory;

final class Anthropic
{
    /**
     * Creates a new Open AI Client with the given API token.
     */
    public static function client(string $apiKey, ?string $organization = null): Client
    {
        return self::factory()
            ->withApiKey($apiKey)
            ->withOrganization($organization)
            ->withHttpHeader('Anthropic-Beta', 'assistants=v1')
            ->make();
    }

    /**
     * Creates a new factory instance to configure a custom Open AI Client
     */
    public static function factory(): Factory
    {
        return new Factory();
    }
}
