<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

final class RetrieveResponseCapabilitiesEffort
{
    private function __construct(
        public readonly bool $supported,
        public readonly RetrieveResponseCapabilitySupport $low,
        public readonly RetrieveResponseCapabilitySupport $medium,
        public readonly RetrieveResponseCapabilitySupport $high,
        public readonly RetrieveResponseCapabilitySupport $max,
    ) {}

    /**
     * @param  array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['supported'],
            RetrieveResponseCapabilitySupport::from($attributes['low']),
            RetrieveResponseCapabilitySupport::from($attributes['medium']),
            RetrieveResponseCapabilitySupport::from($attributes['high']),
            RetrieveResponseCapabilitySupport::from($attributes['max']),
        );
    }

    /**
     * @return array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}
     */
    public function toArray(): array
    {
        return [
            'supported' => $this->supported,
            'low' => $this->low->toArray(),
            'medium' => $this->medium->toArray(),
            'high' => $this->high->toArray(),
            'max' => $this->max->toArray(),
        ];
    }
}
