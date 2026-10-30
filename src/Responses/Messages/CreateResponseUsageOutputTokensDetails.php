<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateResponseUsageOutputTokensDetails
{
    /**
     * @param  array{thinking_tokens?: int}  $attributes
     */
    private function __construct(
        public readonly int $thinkingTokens,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{thinking_tokens?: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['thinking_tokens'] ?? 0,
            $attributes,
        );
    }

    /**
     * @return array{thinking_tokens: int}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'thinking_tokens' => $this->thinkingTokens,
        ]);
    }
}
