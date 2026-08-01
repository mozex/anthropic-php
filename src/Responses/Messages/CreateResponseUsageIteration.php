<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseUsageIteration
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheReadInputTokens,
        public readonly int $cacheCreationInputTokens,
        public readonly ?CreateResponseUsageCacheCreation $cacheCreation,
    ) {}

    /**
     * @param  array{type: string, model?: string|null, input_tokens: int, output_tokens: int, cache_read_input_tokens?: int, cache_creation_input_tokens?: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['model'] ?? null,
            $attributes['input_tokens'],
            $attributes['output_tokens'],
            $attributes['cache_read_input_tokens'] ?? 0,
            $attributes['cache_creation_input_tokens'] ?? 0,
            isset($attributes['cache_creation']) ? CreateResponseUsageCacheCreation::from($attributes['cache_creation']) : null,
        );
    }

    /**
     * @return array{type: string, model?: string, input_tokens: int, output_tokens: int, cache_read_input_tokens: int, cache_creation_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}}
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->model !== null) {
            $result['model'] = $this->model;
        }

        $result['input_tokens'] = $this->inputTokens;
        $result['output_tokens'] = $this->outputTokens;
        $result['cache_read_input_tokens'] = $this->cacheReadInputTokens;
        $result['cache_creation_input_tokens'] = $this->cacheCreationInputTokens;

        if ($this->cacheCreation !== null) {
            $result['cache_creation'] = $this->cacheCreation->toArray();
        }

        return $result;
    }
}
