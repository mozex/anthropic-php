<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateResponseUsageCacheCreation
{
    /**
     * @param  array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}  $attributes
     */
    private function __construct(
        public readonly int $ephemeral5mInputTokens,
        public readonly int $ephemeral1hInputTokens,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['ephemeral_5m_input_tokens'],
            $attributes['ephemeral_1h_input_tokens'],
            $attributes,
        );
    }

    /**
     * @return array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'ephemeral_5m_input_tokens' => $this->ephemeral5mInputTokens,
            'ephemeral_1h_input_tokens' => $this->ephemeral1hInputTokens,
        ]);
    }
}
