<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

/**
 * Server-side compaction support, returned only under a beta header.
 */
class RetrieveResponseCapabilitiesCompaction
{
    /**
     * @param  array{supported: bool, summarize: array{supported: bool}}  $attributes
     */
    private function __construct(
        public readonly bool $supported,
        public readonly RetrieveResponseCapabilitySupport $summarize,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{supported: bool, summarize: array{supported: bool}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['supported'],
            RetrieveResponseCapabilitySupport::from($attributes['summarize']),
            $attributes,
        );
    }

    /**
     * @return array{supported: bool, summarize: array{supported: bool}}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'supported' => $this->supported,
            'summarize' => $this->summarize->toArray(),
        ]);
    }
}
