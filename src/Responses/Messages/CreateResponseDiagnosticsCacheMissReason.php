<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateResponseDiagnosticsCacheMissReason
{
    /**
     * @param  array{type: string, cache_missed_input_tokens?: int|null}  $attributes
     */
    private function __construct(
        public readonly string $type,
        public readonly ?int $cache_missed_input_tokens,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{type: string, cache_missed_input_tokens?: int|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['cache_missed_input_tokens'] ?? null,
            $attributes,
        );
    }

    /**
     * @return array{type: string, cache_missed_input_tokens?: int|null}
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->cache_missed_input_tokens !== null) {
            $result['cache_missed_input_tokens'] = $this->cache_missed_input_tokens;
        }

        return array_replace($this->attributes, $result);
    }
}
