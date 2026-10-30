<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

class RetrieveResponseCapabilitiesThinking
{
    /**
     * @param  array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}  $attributes
     */
    private function __construct(
        public readonly bool $supported,
        public readonly RetrieveResponseCapabilitiesThinkingTypes $types,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['supported'],
            RetrieveResponseCapabilitiesThinkingTypes::from($attributes['types']),
            $attributes,
        );
    }

    /**
     * @return array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'supported' => $this->supported,
            'types' => $this->types->toArray(),
        ]);
    }
}
