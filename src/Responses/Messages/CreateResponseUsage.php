<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use Closure;

class CreateResponseUsage
{
    /**
     * @param  array<int, CreateResponseUsageIteration>|null  $iterations
     * @param  array{status: array{type: string, reason?: string, remove_to_redeem?: array<int, string>|null}}|null  $fallbackCredit
     * @param  array{input_tokens: int, output_tokens: int, cache_creation_input_tokens?: int|null, cache_read_input_tokens?: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null, service_tier?: string|null, server_tool_use?: array{web_search_requests?: int, web_fetch_requests?: int, code_execution_requests?: int, tool_search_requests?: int}|null, inference_geo?: string|null, output_tokens_details?: array{thinking_tokens?: int}|null, speed?: string|null, iterations?: array<int, array{type: string, model?: string|null, input_tokens: int, output_tokens: int, cache_read_input_tokens?: int, cache_creation_input_tokens?: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null}>|null, fallback_credit?: array{status: array{type: string, reason?: string, remove_to_redeem?: array<int, string>|null}}|null}  $attributes
     */
    private function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheCreationInputTokens,
        public readonly int $cacheReadInputTokens,
        public readonly ?CreateResponseUsageCacheCreation $cacheCreation,
        public readonly ?string $serviceTier,
        public readonly ?CreateResponseUsageServerToolUse $serverToolUse,
        public readonly ?string $inferenceGeo,
        public readonly ?CreateResponseUsageOutputTokensDetails $outputTokensDetails,
        public readonly ?string $speed,
        public readonly ?array $iterations,
        public readonly ?array $fallbackCredit,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{input_tokens: int, output_tokens: int, cache_creation_input_tokens?: int|null, cache_read_input_tokens?: int|null, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null, service_tier?: string|null, server_tool_use?: array{web_search_requests?: int, web_fetch_requests?: int, code_execution_requests?: int, tool_search_requests?: int}|null, inference_geo?: string|null, output_tokens_details?: array{thinking_tokens?: int}|null, speed?: string|null, iterations?: array<int, array{type: string, model?: string|null, input_tokens: int, output_tokens: int, cache_read_input_tokens?: int, cache_creation_input_tokens?: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null}>|null, fallback_credit?: array{status: array{type: string, reason?: string, remove_to_redeem?: array<int, string>|null}}|null}  $attributes
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
            isset($attributes['output_tokens_details']) ? CreateResponseUsageOutputTokensDetails::from($attributes['output_tokens_details']) : null,
            $attributes['speed'] ?? null,
            isset($attributes['iterations']) ? array_map(
                static fn (array $iteration): CreateResponseUsageIteration => CreateResponseUsageIteration::from($iteration),
                $attributes['iterations'],
            ) : null,
            $attributes['fallback_credit'] ?? null,
            $attributes,
        );
    }

    /**
     * @return array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null, service_tier?: string|null, server_tool_use?: array<string, int>|null, inference_geo?: string|null, output_tokens_details?: array{thinking_tokens?: int}|null, speed?: string|null, iterations?: array<int, array{type: string, model?: string|null, input_tokens: int, output_tokens: int, cache_read_input_tokens?: int, cache_creation_input_tokens?: int, cache_creation?: array{ephemeral_5m_input_tokens: int, ephemeral_1h_input_tokens: int}|null}>|null, fallback_credit?: array{status: array{type: string, reason?: string, remove_to_redeem?: array<int, string>|null}}|null}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, array_filter([
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'cache_creation' => $this->cacheCreation?->toArray(),
            'service_tier' => $this->serviceTier,
            'server_tool_use' => $this->serverToolUse?->toArray(),
            'inference_geo' => $this->inferenceGeo,
            'output_tokens_details' => $this->outputTokensDetails?->toArray(),
            'speed' => $this->speed,
            'iterations' => $this->iterations === null ? null : array_map(
                static fn (CreateResponseUsageIteration $iteration): array => $iteration->toArray(),
                $this->iterations,
            ),
        ], fn (mixed $value): bool => ! is_null($value)));
    }

    /**
     * The input tokens the call was billed for: plain input, cache writes and cache reads. Counted across `iterations`
     * when the API lists them (compaction, the advisor, a server-side fallback), which the top-level counts leave out.
     */
    public function billedInputTokens(): int
    {
        return $this->sum(fn (int $input, int $output, int $write, int $read): int => $input + $write + $read);
    }

    /**
     * The output tokens the call was billed for, across `iterations` when the API lists them.
     */
    public function billedOutputTokens(): int
    {
        return $this->sum(fn (int $input, int $output, int $write, int $read): int => $output);
    }

    /**
     * Every token the call was billed for: input, cache writes, cache reads and output, across `iterations`.
     */
    public function billedTokens(): int
    {
        return $this->billedInputTokens() + $this->billedOutputTokens();
    }

    /**
     * The tokens rate limits count: input, cache writes and output, across `iterations`. Cache reads are left out,
     * since they don't count toward the input-tokens-per-minute limit on most models.
     */
    public function rateLimitTokens(): int
    {
        return $this->sum(fn (int $input, int $output, int $write, int $read): int => $input + $write + $output);
    }

    /**
     * @param  Closure(int, int, int, int): int  $count  the input, output, cache-write and cache-read tokens of the top-level usage or of one iteration
     */
    private function sum(Closure $count): int
    {
        if ($this->iterations === null || $this->iterations === []) {
            return $count($this->inputTokens, $this->outputTokens, $this->cacheCreationInputTokens, $this->cacheReadInputTokens);
        }

        return array_sum(array_map(
            fn (CreateResponseUsageIteration $iteration): int => $count($iteration->inputTokens, $iteration->outputTokens, $iteration->cacheCreationInputTokens, $iteration->cacheReadInputTokens),
            $this->iterations,
        ));
    }
}
