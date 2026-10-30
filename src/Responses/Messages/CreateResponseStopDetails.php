<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateResponseStopDetails
{
    /**
     * @param  array{type: string, category?: string|null, explanation?: string|null, recommended_model?: string|null, fallback_credit_token?: string|null, fallback_has_prefill_claim?: bool|null}  $attributes
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $category,
        public readonly ?string $explanation,
        public readonly ?string $recommended_model,
        public readonly ?string $fallback_credit_token,
        public readonly ?bool $fallback_has_prefill_claim,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{type: string, category?: string|null, explanation?: string|null, recommended_model?: string|null, fallback_credit_token?: string|null, fallback_has_prefill_claim?: bool|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['category'] ?? null,
            $attributes['explanation'] ?? null,
            $attributes['recommended_model'] ?? null,
            $attributes['fallback_credit_token'] ?? null,
            $attributes['fallback_has_prefill_claim'] ?? null,
            $attributes,
        );
    }

    /**
     * @return array{type: string, category: string|null, explanation: string|null, recommended_model?: string|null, fallback_credit_token?: string|null, fallback_has_prefill_claim?: bool|null}
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'category' => $this->category,
            'explanation' => $this->explanation,
        ];

        if ($this->recommended_model !== null) {
            $result['recommended_model'] = $this->recommended_model;
        }

        if ($this->fallback_credit_token !== null) {
            $result['fallback_credit_token'] = $this->fallback_credit_token;
        }

        if ($this->fallback_has_prefill_claim !== null) {
            $result['fallback_has_prefill_claim'] = $this->fallback_has_prefill_claim;
        }

        return array_replace($this->attributes, $result);
    }
}
