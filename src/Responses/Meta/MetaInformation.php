<?php

namespace Anthropic\Responses\Meta;

use Anthropic\Contracts\MetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;

/**
 * @implements MetaInformationContract<array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string, anthropic-ratelimit-input-tokens-limit?: int, anthropic-ratelimit-input-tokens-remaining?: int, anthropic-ratelimit-input-tokens-reset?: string, anthropic-ratelimit-output-tokens-limit?: int, anthropic-ratelimit-output-tokens-remaining?: int, anthropic-ratelimit-output-tokens-reset?: string, custom?: array<string, string>}>
 */
final class MetaInformation implements MetaInformationContract
{
    /**
     * @use ArrayAccessible<array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string, anthropic-ratelimit-input-tokens-limit?: int, anthropic-ratelimit-input-tokens-remaining?: int, anthropic-ratelimit-input-tokens-reset?: string, anthropic-ratelimit-output-tokens-limit?: int, anthropic-ratelimit-output-tokens-remaining?: int, anthropic-ratelimit-output-tokens-reset?: string, custom?: array<string, string>}>
     */
    use ArrayAccessible;

    private function __construct(
        public ?string $requestId,
        public readonly ?MetaInformationRateLimit $requestLimit,
        public readonly ?MetaInformationRateLimit $tokenLimit,
        public readonly ?MetaInformationRateLimit $inputTokenLimit,
        public readonly ?MetaInformationRateLimit $outputTokenLimit,
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
        ];

        $requestId = $headers['request-id'][0] ?? null;

        if (isset($headers['anthropic-ratelimit-requests-remaining'][0])) {
            $requestLimit = MetaInformationRateLimit::from([
                'limit' => isset($headers['anthropic-ratelimit-requests-limit'][0]) ? (int) $headers['anthropic-ratelimit-requests-limit'][0] : null,
                'remaining' => (int) $headers['anthropic-ratelimit-requests-remaining'][0],
                'reset' => $headers['anthropic-ratelimit-requests-reset'][0] ?? null,
            ]);
        } else {
            $requestLimit = null;
        }

        if (isset($headers['anthropic-ratelimit-tokens-remaining'][0])) {
            $tokenLimit = MetaInformationRateLimit::from([
                'limit' => isset($headers['anthropic-ratelimit-tokens-limit'][0]) ? (int) $headers['anthropic-ratelimit-tokens-limit'][0] : null,
                'remaining' => (int) $headers['anthropic-ratelimit-tokens-remaining'][0],
                'reset' => $headers['anthropic-ratelimit-tokens-reset'][0] ?? null,
            ]);
        } else {
            $tokenLimit = null;
        }

        if (isset($headers['anthropic-ratelimit-input-tokens-remaining'][0])) {
            $inputTokenLimit = MetaInformationRateLimit::from([
                'limit' => isset($headers['anthropic-ratelimit-input-tokens-limit'][0]) ? (int) $headers['anthropic-ratelimit-input-tokens-limit'][0] : null,
                'remaining' => (int) $headers['anthropic-ratelimit-input-tokens-remaining'][0],
                'reset' => $headers['anthropic-ratelimit-input-tokens-reset'][0] ?? null,
            ]);
        } else {
            $inputTokenLimit = null;
        }

        if (isset($headers['anthropic-ratelimit-output-tokens-remaining'][0])) {
            $outputTokenLimit = MetaInformationRateLimit::from([
                'limit' => isset($headers['anthropic-ratelimit-output-tokens-limit'][0]) ? (int) $headers['anthropic-ratelimit-output-tokens-limit'][0] : null,
                'remaining' => (int) $headers['anthropic-ratelimit-output-tokens-remaining'][0],
                'reset' => $headers['anthropic-ratelimit-output-tokens-reset'][0] ?? null,
            ]);
        } else {
            $outputTokenLimit = null;
        }

        $custom = MetaInformationCustom::from($headers, $knownHeaders);

        return new self(
            $requestId,
            $requestLimit,
            $tokenLimit,
            $inputTokenLimit,
            $outputTokenLimit,
            $custom,
        );
    }

    /**
     * @return array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string, anthropic-ratelimit-input-tokens-limit?: int, anthropic-ratelimit-input-tokens-remaining?: int, anthropic-ratelimit-input-tokens-reset?: string, anthropic-ratelimit-output-tokens-limit?: int, anthropic-ratelimit-output-tokens-remaining?: int, anthropic-ratelimit-output-tokens-reset?: string, custom?: array<string, string>}
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
            'request-id' => $this->requestId,
            'custom' => $this->custom->toArray() ?: null,
        ], fn (array|string|int|null $value): bool => ! is_null($value));
    }
}
