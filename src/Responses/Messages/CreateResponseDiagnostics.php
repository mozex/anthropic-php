<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseDiagnostics
{
    private function __construct(
        public readonly ?CreateResponseDiagnosticsCacheMissReason $cache_miss_reason,
    ) {}

    /**
     * @param  array{cache_miss_reason?: array{type: string, cache_missed_input_tokens?: int|null}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            isset($attributes['cache_miss_reason']) ? CreateResponseDiagnosticsCacheMissReason::from($attributes['cache_miss_reason']) : null,
        );
    }

    /**
     * @return array{cache_miss_reason: array{type: string, cache_missed_input_tokens?: int}|null}
     */
    public function toArray(): array
    {
        return [
            'cache_miss_reason' => $this->cache_miss_reason?->toArray(),
        ];
    }
}
