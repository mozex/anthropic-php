<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseUsageOutputTokensDetails
{
    private function __construct(
        public readonly int $thinkingTokens,
    ) {}

    /**
     * @param  array{thinking_tokens?: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['thinking_tokens'] ?? 0,
        );
    }

    /**
     * @return array{thinking_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'thinking_tokens' => $this->thinkingTokens,
        ];
    }
}
