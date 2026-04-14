<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

final class RetrieveResponseCapabilitySupport
{
    private function __construct(
        public readonly bool $supported,
    ) {}

    /**
     * @param  array{supported: bool}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['supported'],
        );
    }

    /**
     * @return array{supported: bool}
     */
    public function toArray(): array
    {
        return [
            'supported' => $this->supported,
        ];
    }
}
