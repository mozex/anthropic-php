<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

final class RetrieveResponseCapabilitiesThinkingTypes
{
    private function __construct(
        public readonly RetrieveResponseCapabilitySupport $adaptive,
        public readonly RetrieveResponseCapabilitySupport $enabled,
    ) {}

    /**
     * @param  array{adaptive: array{supported: bool}, enabled: array{supported: bool}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            RetrieveResponseCapabilitySupport::from($attributes['adaptive']),
            RetrieveResponseCapabilitySupport::from($attributes['enabled']),
        );
    }

    /**
     * @return array{adaptive: array{supported: bool}, enabled: array{supported: bool}}
     */
    public function toArray(): array
    {
        return [
            'adaptive' => $this->adaptive->toArray(),
            'enabled' => $this->enabled->toArray(),
        ];
    }
}
