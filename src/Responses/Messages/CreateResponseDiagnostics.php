<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateResponseDiagnostics
{
    /**
     * @param  array{cache_miss_reason?: array{type: string, cache_missed_input_tokens?: int|null}|null}  $attributes
     */
    private function __construct(
        public readonly ?CreateResponseDiagnosticsCacheMissReason $cache_miss_reason,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{cache_miss_reason?: array{type: string, cache_missed_input_tokens?: int|null}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            isset($attributes['cache_miss_reason']) ? CreateResponseDiagnosticsCacheMissReason::from($attributes['cache_miss_reason']) : null,
            $attributes,
        );
    }

    /**
     * @return array{cache_miss_reason: array{type: string, cache_missed_input_tokens?: int|null}|null}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'cache_miss_reason' => $this->cache_miss_reason?->toArray(),
        ]);
    }
}
