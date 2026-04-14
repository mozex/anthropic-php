<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseStopDetails
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $category,
        public readonly ?string $explanation,
    ) {}

    /**
     * @param  array{type: string, category?: string|null, explanation?: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['category'] ?? null,
            $attributes['explanation'] ?? null,
        );
    }

    /**
     * @return array{type: string, category: string|null, explanation: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'category' => $this->category,
            'explanation' => $this->explanation,
        ];
    }
}
