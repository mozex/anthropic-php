<?php

declare(strict_types=1);

namespace Anthropic\Responses\Meta;

use Anthropic\Contracts\MetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;

/**
 * @implements MetaInformationContract<array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string, anthropic-ratelimit-input-tokens-limit?: int, anthropic-ratelimit-input-tokens-remaining?: int, anthropic-ratelimit-input-tokens-reset?: string, anthropic-ratelimit-output-tokens-limit?: int, anthropic-ratelimit-output-tokens-remaining?: int, anthropic-ratelimit-output-tokens-reset?: string, anthropic-priority-input-tokens-limit?: int, anthropic-priority-input-tokens-remaining?: int, anthropic-priority-input-tokens-reset?: string, anthropic-priority-output-tokens-limit?: int, anthropic-priority-output-tokens-remaining?: int, anthropic-priority-output-tokens-reset?: string, anthropic-fast-input-tokens-limit?: int, anthropic-fast-input-tokens-remaining?: int, anthropic-fast-input-tokens-reset?: string, anthropic-fast-output-tokens-limit?: int, anthropic-fast-output-tokens-remaining?: int, anthropic-fast-output-tokens-reset?: string, anthropic-organization-id?: string, anthropic-workspace-id?: string, retry-after?: int, custom?: array<string, string>}>
 */
class MetaInformation implements MetaInformationContract
{
    /**
     * @use ArrayAccessible<array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string, anthropic-ratelimit-input-tokens-limit?: int, anthropic-ratelimit-input-tokens-remaining?: int, anthropic-ratelimit-input-tokens-reset?: string, anthropic-ratelimit-output-tokens-limit?: int, anthropic-ratelimit-output-tokens-remaining?: int, anthropic-ratelimit-output-tokens-reset?: string, anthropic-priority-input-tokens-limit?: int, anthropic-priority-input-tokens-remaining?: int, anthropic-priority-input-tokens-reset?: string, anthropic-priority-output-tokens-limit?: int, anthropic-priority-output-tokens-remaining?: int, anthropic-priority-output-tokens-reset?: string, anthropic-fast-input-tokens-limit?: int, anthropic-fast-input-tokens-remaining?: int, anthropic-fast-input-tokens-reset?: string, anthropic-fast-output-tokens-limit?: int, anthropic-fast-output-tokens-remaining?: int, anthropic-fast-output-tokens-reset?: string, anthropic-organization-id?: string, anthropic-workspace-id?: string, retry-after?: int, custom?: array<string, string>}>
     */
    use ArrayAccessible;

    private function __construct(
        public ?string $requestId,
        public readonly ?MetaInformationRateLimit $requestLimit,
        public readonly ?MetaInformationRateLimit $tokenLimit,
        public readonly ?MetaInformationRateLimit $inputTokenLimit,
        public readonly ?MetaInformationRateLimit $outputTokenLimit,
        public readonly ?MetaInformationRateLimit $priorityInputTokenLimit,
        public readonly ?MetaInformationRateLimit $priorityOutputTokenLimit,
        public readonly ?MetaInformationRateLimit $fastInputTokenLimit,
        public readonly ?MetaInformationRateLimit $fastOutputTokenLimit,
        public readonly ?string $organizationId,
        public readonly ?string $workspaceId,
        public readonly ?int $retryAfter,
        public readonly MetaInformationCustom $custom,
    ) {}

    /**
     * @param  array<array<string>>  $headers
     */
    public static function from(array $headers): self
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        $knownHeaders = [
            'request-id',
            'anthropic-ratelimit-requests-limit',
            'anthropic-ratelimit-requests-remaining',
            'anthropic-ratelimit-requests-reset',
            'anthropic-ratelimit-tokens-limit',
            'anthropic-ratelimit-tokens-remaining',
            'anthropic-ratelimit-tokens-reset',
            'anthropic-ratelimit-input-tokens-limit',
            'anthropic-ratelimit-input-tokens-remaining',
            'anthropic-ratelimit-input-tokens-reset',
            'anthropic-ratelimit-output-tokens-limit',
            'anthropic-ratelimit-output-tokens-remaining',
            'anthropic-ratelimit-output-tokens-reset',
            'anthropic-priority-input-tokens-limit',
            'anthropic-priority-input-tokens-remaining',
            'anthropic-priority-input-tokens-reset',
            'anthropic-priority-output-tokens-limit',
            'anthropic-priority-output-tokens-remaining',
            'anthropic-priority-output-tokens-reset',
        ];

        $requestId = $headers['request-id'][0] ?? null;

        $custom = MetaInformationCustom::from($headers, $knownHeaders);

        // whole seconds; an HTTP date stays readable, as sent, in custom
        $retryAfter = (string) ($headers['retry-after'][0] ?? '');

        return new self(
            $requestId,
            self::rateLimit($headers, 'anthropic-ratelimit-requests'),
            self::rateLimit($headers, 'anthropic-ratelimit-tokens'),
            self::rateLimit($headers, 'anthropic-ratelimit-input-tokens'),
            self::rateLimit($headers, 'anthropic-ratelimit-output-tokens'),
            self::rateLimit($headers, 'anthropic-priority-input-tokens'),
            self::rateLimit($headers, 'anthropic-priority-output-tokens'),
            self::rateLimit($headers, 'anthropic-fast-input-tokens'),
            self::rateLimit($headers, 'anthropic-fast-output-tokens'),
            isset($headers['anthropic-organization-id'][0]) ? (string) $headers['anthropic-organization-id'][0] : null,
            isset($headers['anthropic-workspace-id'][0]) ? (string) $headers['anthropic-workspace-id'][0] : null,
            preg_match('/^\d+$/', $retryAfter) === 1 ? (int) $retryAfter : null,
            $custom,
        );
    }

    /**
     * Reads one `{prefix}-limit`, `-remaining` and `-reset` header family; null when `-remaining` is absent.
     *
     * @param  array<array<string>>  $headers
     */
    protected static function rateLimit(array $headers, string $prefix): ?MetaInformationRateLimit
    {
        if (! isset($headers["{$prefix}-remaining"][0])) {
            return null;
        }

        return MetaInformationRateLimit::from([
            'limit' => isset($headers["{$prefix}-limit"][0]) ? (int) $headers["{$prefix}-limit"][0] : null,
            'remaining' => (int) $headers["{$prefix}-remaining"][0],
            'reset' => isset($headers["{$prefix}-reset"][0]) ? (string) $headers["{$prefix}-reset"][0] : null,
        ]);
    }

    /**
     * @return array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string, anthropic-ratelimit-input-tokens-limit?: int, anthropic-ratelimit-input-tokens-remaining?: int, anthropic-ratelimit-input-tokens-reset?: string, anthropic-ratelimit-output-tokens-limit?: int, anthropic-ratelimit-output-tokens-remaining?: int, anthropic-ratelimit-output-tokens-reset?: string, anthropic-priority-input-tokens-limit?: int, anthropic-priority-input-tokens-remaining?: int, anthropic-priority-input-tokens-reset?: string, anthropic-priority-output-tokens-limit?: int, anthropic-priority-output-tokens-remaining?: int, anthropic-priority-output-tokens-reset?: string, anthropic-fast-input-tokens-limit?: int, anthropic-fast-input-tokens-remaining?: int, anthropic-fast-input-tokens-reset?: string, anthropic-fast-output-tokens-limit?: int, anthropic-fast-output-tokens-remaining?: int, anthropic-fast-output-tokens-reset?: string, anthropic-organization-id?: string, anthropic-workspace-id?: string, retry-after?: int, custom?: array<string, string>}
     */
    public function toArray(): array
    {
        return array_filter([
            'anthropic-ratelimit-requests-limit' => $this->requestLimit?->limit,
            'anthropic-ratelimit-tokens-limit' => $this->tokenLimit?->limit,
            'anthropic-ratelimit-requests-remaining' => $this->requestLimit?->remaining,
            'anthropic-ratelimit-tokens-remaining' => $this->tokenLimit?->remaining,
            'anthropic-ratelimit-requests-reset' => $this->requestLimit?->reset,
            'anthropic-ratelimit-tokens-reset' => $this->tokenLimit?->reset,
            'anthropic-ratelimit-input-tokens-limit' => $this->inputTokenLimit?->limit,
            'anthropic-ratelimit-input-tokens-remaining' => $this->inputTokenLimit?->remaining,
            'anthropic-ratelimit-input-tokens-reset' => $this->inputTokenLimit?->reset,
            'anthropic-ratelimit-output-tokens-limit' => $this->outputTokenLimit?->limit,
            'anthropic-ratelimit-output-tokens-remaining' => $this->outputTokenLimit?->remaining,
            'anthropic-ratelimit-output-tokens-reset' => $this->outputTokenLimit?->reset,
            'anthropic-priority-input-tokens-limit' => $this->priorityInputTokenLimit?->limit,
            'anthropic-priority-input-tokens-remaining' => $this->priorityInputTokenLimit?->remaining,
            'anthropic-priority-input-tokens-reset' => $this->priorityInputTokenLimit?->reset,
            'anthropic-priority-output-tokens-limit' => $this->priorityOutputTokenLimit?->limit,
            'anthropic-priority-output-tokens-remaining' => $this->priorityOutputTokenLimit?->remaining,
            'anthropic-priority-output-tokens-reset' => $this->priorityOutputTokenLimit?->reset,
            'anthropic-fast-input-tokens-limit' => $this->fastInputTokenLimit?->limit,
            'anthropic-fast-input-tokens-remaining' => $this->fastInputTokenLimit?->remaining,
            'anthropic-fast-input-tokens-reset' => $this->fastInputTokenLimit?->reset,
            'anthropic-fast-output-tokens-limit' => $this->fastOutputTokenLimit?->limit,
            'anthropic-fast-output-tokens-remaining' => $this->fastOutputTokenLimit?->remaining,
            'anthropic-fast-output-tokens-reset' => $this->fastOutputTokenLimit?->reset,
            'anthropic-organization-id' => $this->organizationId,
            'anthropic-workspace-id' => $this->workspaceId,
            'retry-after' => $this->retryAfter,
            'request-id' => $this->requestId,
            'custom' => $this->custom->toArray() ?: null,
        ], fn (array|string|int|null $value): bool => ! is_null($value));
    }
}
