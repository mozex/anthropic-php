<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

final class RetrieveResponseCapabilitiesContextManagement
{
    /**
     * @param  array<string, RetrieveResponseCapabilitySupport>  $strategies
     */
    private function __construct(
        public readonly bool $supported,
        public readonly array $strategies,
    ) {}

    /**
     * @param  array<string, bool|array{supported: bool}>  $attributes
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
        );
    }

    /**
     * @return array<string, bool|array{supported: bool}>
     */
    public function toArray(): array
    {
        $result = ['supported' => $this->supported];

        foreach ($this->strategies as $key => $strategy) {
            $result[$key] = $strategy->toArray();
        }

        return $result;
    }
}
