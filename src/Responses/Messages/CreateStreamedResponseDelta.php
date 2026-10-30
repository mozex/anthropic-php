<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateStreamedResponseDelta
{
    /**
     * @param  array<string, mixed>|null  $citation
     * @param  array{id: string, expires_at: string, skills?: array<int, array{type: string, skill_id: string, version: string}>|null}|null  $container
     * @param  array{type?: string, text?: string|null, partial_json?: string|null, stop_reason?: string, stop_sequence?: string|null, thinking?: string|null, signature?: string|null, citation?: array<string, mixed>|null, content?: string|null, encrypted_content?: string|null, estimated_tokens?: int|null, stop_details?: array{type: string, category?: string|null, explanation?: string|null, recommended_model?: string|null, fallback_credit_token?: string|null, fallback_has_prefill_claim?: bool|null}|null, container?: array{id: string, expires_at: string, skills?: array<int, array{type: string, skill_id: string, version: string}>|null}|null}  $attributes
     */
    private function __construct(
        public readonly ?string $type,
        public readonly ?string $text,
        public readonly ?string $partial_json,
        public readonly ?string $stop_reason,
        public readonly ?string $stop_sequence,
        public readonly ?string $thinking,
        public readonly ?string $signature,
        public readonly ?array $citation,
        public readonly ?string $content,
        public readonly ?string $encrypted_content,
        public readonly ?int $estimated_tokens,
        public readonly ?CreateResponseStopDetails $stop_details,
        public readonly ?array $container,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{type?: string, text?: string|null, partial_json?: string|null, stop_reason?: string, stop_sequence?: string|null, thinking?: string|null, signature?: string|null, citation?: array<string, mixed>|null, content?: string|null, encrypted_content?: string|null, estimated_tokens?: int|null, stop_details?: array{type: string, category?: string|null, explanation?: string|null, recommended_model?: string|null, fallback_credit_token?: string|null, fallback_has_prefill_claim?: bool|null}|null, container?: array{id: string, expires_at: string, skills?: array<int, array{type: string, skill_id: string, version: string}>|null}|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'] ?? null,
            $attributes['text'] ?? null,
            $attributes['partial_json'] ?? null,
            $attributes['stop_reason'] ?? null,
            $attributes['stop_sequence'] ?? null,
            $attributes['thinking'] ?? null,
            $attributes['signature'] ?? null,
            $attributes['citation'] ?? null,
            $attributes['content'] ?? null,
            $attributes['encrypted_content'] ?? null,
            $attributes['estimated_tokens'] ?? null,
            isset($attributes['stop_details']) ? CreateResponseStopDetails::from($attributes['stop_details']) : null,
            $attributes['container'] ?? null,
            $attributes,
        );
    }

    /**
     * @return array{type: string|null, text: string|null, partial_json?: string|null, stop_reason: string|null, stop_sequence: string|null, thinking?: string|null, signature?: string|null, citation?: array<string, mixed>|null, content?: string|null, encrypted_content?: string|null, estimated_tokens?: int|null, stop_details?: array{type: string, category?: string|null, explanation?: string|null, recommended_model?: string|null, fallback_credit_token?: string|null, fallback_has_prefill_claim?: bool|null}|null, container?: array{id: string, expires_at: string, skills?: array<int, array{type: string, skill_id: string, version: string}>|null}|null}
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'text' => $this->text,
            'stop_reason' => $this->stop_reason,
            'stop_sequence' => $this->stop_sequence,
        ];

        if ($this->partial_json !== null) {
            $data['partial_json'] = $this->partial_json;
        }

        if ($this->thinking !== null) {
            $data['thinking'] = $this->thinking;
        }

        if ($this->signature !== null) {
            $data['signature'] = $this->signature;
        }

        if ($this->citation !== null) {
            $data['citation'] = $this->citation;
        }

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->stop_details !== null) {
            $data['stop_details'] = $this->stop_details->toArray();
        }

        return array_replace($this->attributes, $data);
    }
}
