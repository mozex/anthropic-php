<?php

declare(strict_types=1);

namespace Anthropic\ValueObjects\Transporter;

use Anthropic\Contracts\StringableContract;

/**
 * @internal
 */
class BaseUri implements StringableContract
{
    /**
     * Creates a new Base URI value object.
     */
    private function __construct(private readonly string $baseUri)
    {
        // ..
    }

    /**
     * Creates a new Base URI value object.
     */
    public static function from(string $baseUri): self
    {
        return new self($baseUri);
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        // A trailing slash would double the one added below.
        $baseUri = rtrim($this->baseUri, '/');

        foreach (['http://', 'https://'] as $protocol) {
            if (str_starts_with($baseUri, $protocol)) {
                return "{$baseUri}/";
            }
        }

        return "https://{$baseUri}/";
    }
}
