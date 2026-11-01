<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Factory;

class Anthropic
{
    /**
     * Creates a new Anthropic Client with the given API token.
     */
    public static function client(#[SensitiveParameter] string $apiKey): Client
    {
        return self::factory()
            ->withApiKey($apiKey)
            ->make();
    }

    /**
     * Creates a new Anthropic Client from the environment variables the official SDKs read:
     * ANTHROPIC_API_KEY (or, without it, ANTHROPIC_AUTH_TOKEN), ANTHROPIC_BASE_URL and ANTHROPIC_CUSTOM_HEADERS.
     */
    public static function fromEnvironment(): Client
    {
        $apiKey = self::environment('ANTHROPIC_API_KEY');
        $authToken = self::environment('ANTHROPIC_AUTH_TOKEN');

        if ($apiKey === null && $authToken === null) {
            throw new InvalidArgumentException('Set ANTHROPIC_API_KEY or ANTHROPIC_AUTH_TOKEN to create a client from the environment.');
        }

        $factory = self::factory();

        // One credential, as in the official SDKs: the key when both are set.
        if ($apiKey !== null) {
            $factory->withApiKey($apiKey);
        } elseif ($authToken !== null) {
            $factory->withAuthToken($authToken);
        }

        $factory->withBaseUrl(self::environment('ANTHROPIC_BASE_URL') ?? '');

        // One "Name: value" header per line; a line that isn't one is skipped.
        foreach (preg_split('/\r\n|\r|\n/', self::environment('ANTHROPIC_CUSTOM_HEADERS') ?? '') ?: [] as $line) {
            if (preg_match("/^\s*([!#$%&'*+.^_`|~0-9A-Za-z-]+)\s*:(.*)$/", $line, $matches) === 1) {
                $factory->withHttpHeader($matches[1], trim($matches[2]));
            }
        }

        return $factory->make();
    }

    /**
     * Reads an environment variable from $_ENV, then $_SERVER, then the process: the first non-blank value, trimmed.
     */
    private static function environment(string $name): ?string
    {
        foreach ([$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Creates a new factory instance to configure a custom Anthropic Client
     */
    public static function factory(): Factory
    {
        return new Factory;
    }
}
