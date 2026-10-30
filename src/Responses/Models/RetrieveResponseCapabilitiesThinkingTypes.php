<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

class RetrieveResponseCapabilitiesThinkingTypes
{
    /**
     * @param  array{adaptive: array{supported: bool}, enabled: array{supported: bool}}  $attributes
     */
    private function __construct(
        public readonly RetrieveResponseCapabilitySupport $adaptive,
        public readonly RetrieveResponseCapabilitySupport $enabled,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{adaptive: array{supported: bool}, enabled: array{supported: bool}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            RetrieveResponseCapabilitySupport::from($attributes['adaptive']),
            RetrieveResponseCapabilitySupport::from($attributes['enabled']),
            $attributes,
        );
    }

    /**
     * @return array{adaptive: array{supported: bool}, enabled: array{supported: bool}}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'adaptive' => $this->adaptive->toArray(),
            'enabled' => $this->enabled->toArray(),
        ]);
    }
}
