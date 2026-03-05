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
        public readonly ?CreateResponseUsageCacheCreation $cacheCreation,
        public readonly ?string $serviceTier,
        public readonly ?CreateResponseUsageServerToolUse $serverToolUse,
    ) {}

    /**
     * @param  array{input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array{web_search_requests: int}}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['input_tokens'] ?? null,
            $attributes['output_tokens'] ?? null,
            $attributes['cache_creation_input_tokens'] ?? null,
            $attributes['cache_read_input_tokens'] ?? null,
            isset($attributes['cache_creation']) ? CreateResponseUsageCacheCreation::from($attributes['cache_creation']) : null,
            $attributes['service_tier'] ?? null,
            isset($attributes['server_tool_use']) ? CreateResponseUsageServerToolUse::from($attributes['server_tool_use']) : null,
        );
    }

    /**
     * @return array{input_tokens: int|null, output_tokens: int|null, cache_creation_input_tokens: int|null, cache_read_input_tokens: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}, service_tier?: string, server_tool_use?: array{web_search_requests: int}}
     */
    public function toArray(): array
    {
        $data = [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
        ];

        if ($this->cacheCreation !== null) {
            $data['cache_creation'] = $this->cacheCreation->toArray();
        }

        if ($this->serviceTier !== null) {
            $data['service_tier'] = $this->serviceTier;
        }

        if ($this->serverToolUse !== null) {
            $data['server_tool_use'] = $this->serverToolUse->toArray();
        }

        return $data;
    }
}
