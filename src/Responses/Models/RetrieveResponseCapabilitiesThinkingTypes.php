<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

class RetrieveResponseCapabilitiesThinkingTypes
{
    /**
     * @param  array{adaptive: array{supported: bool}, enabled: array{supported: bool}, disabled?: array{supported: bool}, between_tools?: array{supported: bool}}  $attributes
     */
    private function __construct(
        public readonly RetrieveResponseCapabilitySupport $adaptive,
        public readonly RetrieveResponseCapabilitySupport $enabled,
        public readonly ?RetrieveResponseCapabilitySupport $disabled,
        public readonly ?RetrieveResponseCapabilitySupport $betweenTools,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{adaptive: array{supported: bool}, enabled: array{supported: bool}, disabled?: array{supported: bool}, between_tools?: array{supported: bool}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            RetrieveResponseCapabilitySupport::from($attributes['adaptive']),
            RetrieveResponseCapabilitySupport::from($attributes['enabled']),
            isset($attributes['disabled']) ? RetrieveResponseCapabilitySupport::from($attributes['disabled']) : null,
            isset($attributes['between_tools']) ? RetrieveResponseCapabilitySupport::from($attributes['between_tools']) : null,
            $attributes,
        );
    }

    /**
     * @return array{adaptive: array{supported: bool}, enabled: array{supported: bool}, disabled?: array{supported: bool}, between_tools?: array{supported: bool}}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, array_filter([
            'adaptive' => $this->adaptive->toArray(),
            'enabled' => $this->enabled->toArray(),
            'disabled' => $this->disabled?->toArray(),
            'between_tools' => $this->betweenTools?->toArray(),
        ], fn (mixed $value): bool => ! is_null($value)));
    }
}
