<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

class RetrieveResponseCapabilitySupport
{
    /**
     * @param  array{supported: bool}  $attributes
     */
    private function __construct(
        public readonly bool $supported,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{supported: bool}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['supported'],
            $attributes,
        );
    }

    /**
     * @return array{supported: bool}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'supported' => $this->supported,
        ]);
    }
}
