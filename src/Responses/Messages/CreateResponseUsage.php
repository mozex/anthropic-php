<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseUsage
{
    private function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
    ) {
    }

    /**
     * @param  array{input_tokens: int, output_tokens: int}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['input_tokens'],
            $attributes['output_tokens'],
        );
    }

    /**
     * @return array{input_tokens: int, output_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
        ];
    }
}
