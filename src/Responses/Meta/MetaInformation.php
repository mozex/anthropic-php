<?php

namespace Anthropic\Responses\Meta;

use Anthropic\Contracts\MetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;

/**
 * @implements MetaInformationContract<array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string}>
 */
final class MetaInformation implements MetaInformationContract
{
    /**
     * @use ArrayAccessible<array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string}>
     */
    use ArrayAccessible;

    private function __construct(
        public ?string $requestId,
        public readonly ?MetaInformationRateLimit $requestLimit,
        public readonly ?MetaInformationRateLimit $tokenLimit,
    ) {}

    /**
     * @param  array{request-id: string[], anthropic-ratelimit-requests-limit: string[], anthropic-ratelimit-requests-remaining: string[], anthropic-ratelimit-requests-reset: string[], anthropic-ratelimit-tokens-limit: string[], anthropic-ratelimit-tokens-remaining: string[], anthropic-ratelimit-tokens-reset: string[]}  $headers
     */
    public static function from(array $headers): self
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

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

        return new self(
            $requestId,
            $requestLimit,
            $tokenLimit,
        );
    }

    /**
     * @return array{request-id?: string, anthropic-ratelimit-requests-limit?: int, anthropic-ratelimit-tokens-limit?: int, anthropic-ratelimit-requests-remaining?: int, anthropic-ratelimit-tokens-remaining?: int, anthropic-ratelimit-requests-reset?: string, anthropic-ratelimit-tokens-reset?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'anthropic-ratelimit-requests-limit' => $this->requestLimit->limit ?? null,
            'anthropic-ratelimit-tokens-limit' => $this->tokenLimit->limit ?? null,
            'anthropic-ratelimit-requests-remaining' => $this->requestLimit->remaining ?? null,
            'anthropic-ratelimit-tokens-remaining' => $this->tokenLimit->remaining ?? null,
            'anthropic-ratelimit-requests-reset' => $this->requestLimit->reset ?? null,
            'anthropic-ratelimit-tokens-reset' => $this->tokenLimit->reset ?? null,
            'request-id' => $this->requestId,
        ], fn (string|int|null $value): bool => ! is_null($value));
    }
}
