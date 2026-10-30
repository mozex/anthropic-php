<?php

declare(strict_types=1);

namespace Anthropic\ValueObjects\Transporter;

use Anthropic\Enums\Transporter\ContentType;
use Anthropic\ValueObjects\ApiKey;

/**
 * @internal
 */
class Headers
{
    /**
     * Creates a new Headers value object.
     *
     * @param  array<string, string>  $headers
     */
    private function __construct(private readonly array $headers)
    {
        // ..
    }

    /**
     * Creates a new Headers value object
     */
    public static function create(): self
    {
        return new self([]);
    }

    /**
     * Creates a new Headers value object with the given API token.
     */
    public static function withAuthorization(ApiKey $apiKey): self
    {
        return new self([
            'x-api-key' => $apiKey->toString(),
        ]);
    }

    /**
     * Creates a new Headers value object, with the given content type, and the existing headers.
     */
    public function withContentType(ContentType $contentType, string $suffix = ''): self
    {
        return new self([
            ...$this->headers,
            'Content-Type' => $contentType->value.$suffix,
        ]);
    }

    /**
     * Creates a new Headers value object, with the newly added header, and the existing headers.
     */
    public function withCustomHeader(string $name, string $value): self
    {
        // Header names are case-insensitive: drop any spelling of the name, so the new value is the one sent.
        $headers = array_filter(
            $this->headers,
            fn (int|string $key): bool => strcasecmp((string) $key, $name) !== 0,
            ARRAY_FILTER_USE_KEY,
        );

        return new self([
            ...$headers,
            $name => $value,
        ]);
    }

    /**
     * Creates a new Headers value object whose `anthropic-beta` header also lists the given betas, de-duplicated.
     *
     * @param  list<string>  $betas
     */
    public function withBetas(array $betas): self
    {
        $existing = [];

        foreach ($this->headers as $name => $value) {
            if (strcasecmp($name, 'anthropic-beta') === 0) {
                $existing = [...$existing, ...array_map(trim(...), explode(',', $value))];
            }
        }

        $merged = array_values(array_unique(array_filter([...$existing, ...$betas], fn (string $beta): bool => $beta !== '')));

        return $this->withCustomHeader('anthropic-beta', implode(',', $merged));
    }

    /**
     * @return array<string, string> $headers
     */
    public function toArray(): array
    {
        return $this->headers;
    }
}
