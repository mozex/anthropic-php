<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateStreamedResponseUsage
{
    private function __construct(
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
        public readonly ?int $cacheCreationInputTokens,
        public readonly ?int $cacheReadInputTokens,
    ) {}

    /**
     * @param  array{input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['input_tokens'] ?? null,
            $attributes['output_tokens'] ?? null,
            $attributes['cache_creation_input_tokens'] ?? null,
            $attributes['cache_read_input_tokens'] ?? null,
        );
    }

    /**
     * @return array{input_tokens: int|null, output_tokens: int|null, cache_creation_input_tokens: int|null, cache_read_input_tokens: int|null}
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
        ];
    }
}
