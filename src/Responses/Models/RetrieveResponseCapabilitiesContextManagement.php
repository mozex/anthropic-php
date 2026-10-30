<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

class RetrieveResponseCapabilitiesContextManagement
{
    /**
     * @param  array<string, RetrieveResponseCapabilitySupport>  $strategies
     * @param  array<string, bool|array{supported: bool}|null>  $attributes
     */
    private function __construct(
        public readonly bool $supported,
        public readonly array $strategies,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array<string, bool|array{supported: bool}|null>  $attributes
     */
    public static function from(array $attributes): self
    {
        $strategies = [];

        foreach ($attributes as $key => $value) {
            if ($key === 'supported') {
                continue;
            }

            if (is_array($value)) {
                $strategies[$key] = RetrieveResponseCapabilitySupport::from($value);
            }
        }

        $supported = $attributes['supported'] ?? false;

        return new self(
            is_bool($supported) ? $supported : false,
            $strategies,
            $attributes,
        );
    }

    /**
     * @return array<string, bool|array{supported: bool}|null>
     */
    public function toArray(): array
    {
        $result = ['supported' => $this->supported];

        foreach ($this->strategies as $key => $strategy) {
            $result[$key] = $strategy->toArray();
        }

        return array_replace($this->attributes, $result);
    }
}
