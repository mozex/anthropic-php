<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateStreamedResponseDelta
{
    private function __construct(
        public readonly ?string $type,
        public readonly ?string $text,
        public readonly ?string $partial_json,
        public readonly ?string $stop_reason,
        public readonly ?string $stop_sequence,
        public readonly ?string $thinking,
        public readonly ?string $signature,
    ) {}

    /**
     * @param  array{type?: string, text?: string|null, partial_json?: ?string, stop_reason?: string, stop_sequence?: string|null, thinking?: string|null, signature?: string|null}  $attributes
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
        );
    }

    /**
     * @return array{type: string|null, text: string|null, partial_json?: ?string, stop_reason: string|null, stop_sequence: string|null, thinking?: string|null, signature?: string|null}
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

        return $data;
    }
}
