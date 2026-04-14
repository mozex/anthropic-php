<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

final class RetrieveResponseCapabilitiesThinking
{
    private function __construct(
        public readonly bool $supported,
        public readonly RetrieveResponseCapabilitiesThinkingTypes $types,
    ) {}

    /**
     * @param  array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['supported'],
            RetrieveResponseCapabilitiesThinkingTypes::from($attributes['types']),
        );
    }

    /**
     * @return array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}
     */
    public function toArray(): array
    {
        return [
            'supported' => $this->supported,
            'types' => $this->types->toArray(),
        ];
    }
}
