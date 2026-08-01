<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseStopDetails
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $category,
        public readonly ?string $explanation,
        public readonly ?string $recommended_model,
    ) {}

    /**
     * @param  array{type: string, category?: string|null, explanation?: string|null, recommended_model?: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['category'] ?? null,
            $attributes['explanation'] ?? null,
            $attributes['recommended_model'] ?? null,
        );
    }

    /**
     * @return array{type: string, category: string|null, explanation: string|null, recommended_model?: string}
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'category' => $this->category,
            'explanation' => $this->explanation,
        ];

        if ($this->recommended_model !== null) {
            $result['recommended_model'] = $this->recommended_model;
        }

        return $result;
    }
}
