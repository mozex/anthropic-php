<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateResponseContentFallbackTrigger
{
    /**
     * @param  array{type: string, category?: string|null}  $attributes
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $category,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{type: string, category?: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['category'] ?? null,
            $attributes,
        );
    }

    /**
     * @return array{type: string, category?: string|null}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, array_filter([
            'type' => $this->type,
            'category' => $this->category,
        ], fn (mixed $value): bool => ! is_null($value)));
    }
}
