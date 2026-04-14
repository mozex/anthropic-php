<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseUsage
{
    private function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheCreationInputTokens,
        public readonly int $cacheReadInputTokens,
        public readonly ?CreateResponseUsageCacheCreation $cacheCreation,
        public readonly ?string $serviceTier,
        public readonly ?CreateResponseUsageServerToolUse $serverToolUse,
        public readonly ?string $inferenceGeo,
    ) {}

    /**
     * @param  array{input_tokens: int, output_tokens: int, cache_creation_input_tokens?: int|null, cache_read_input_tokens?: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null, service_tier?: string|null, server_tool_use?: array{web_search_requests?: int, web_fetch_requests?: int, code_execution_requests?: int, tool_search_requests?: int}|null, inference_geo?: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['input_tokens'],
            $attributes['output_tokens'],
            $attributes['cache_creation_input_tokens'] ?? 0,
            $attributes['cache_read_input_tokens'] ?? 0,
            isset($attributes['cache_creation']) ? CreateResponseUsageCacheCreation::from($attributes['cache_creation']) : null,
            $attributes['service_tier'] ?? null,
            isset($attributes['server_tool_use']) ? CreateResponseUsageServerToolUse::from($attributes['server_tool_use']) : null,
            $attributes['inference_geo'] ?? null,
        );
    }

    /**
     * @return array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array<string, int>, inference_geo?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'cache_creation' => $this->cacheCreation?->toArray(),
            'service_tier' => $this->serviceTier,
            'server_tool_use' => $this->serverToolUse?->toArray(),
            'inference_geo' => $this->inferenceGeo,
        ], fn (mixed $value): bool => ! is_null($value));
    }
}
