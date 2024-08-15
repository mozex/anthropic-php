<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateStreamedResponseUsage
{
    private function __construct(
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
    ) {}

    /**
     * @param  array{input_tokens?: int, output_tokens?: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['input_tokens'] ?? null,
            $attributes['output_tokens'] ?? null,
        );
    }

    /**
     * @return array{input_tokens: int|null, output_tokens: int|null}
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
        ];
    }
}
