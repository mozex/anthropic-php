<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseContent
{
    /**
     * @param  array<string, mixed>|null  $input
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $text,
        public readonly ?string $id,
        public readonly ?string $name,
        public readonly ?array $input,
        public readonly ?string $thinking,
        public readonly ?string $signature,
        public readonly ?string $data,
    ) {}

    /**
     * @param  array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['text'] ?? null,
            $attributes['id'] ?? null,
            $attributes['name'] ?? null,
            $attributes['input'] ?? null,
            $attributes['thinking'] ?? null,
            $attributes['signature'] ?? null,
            $attributes['data'] ?? null,
        );
    }

    /**
     * @return array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, mixed>|null, thinking?: string|null, signature?: string|null, data?: string|null}
     */
    public function toArray(): array
    {
        return match ($this->type) {
            'thinking' => [
                'type' => $this->type,
                'thinking' => $this->thinking,
                'signature' => $this->signature,
            ],
            'redacted_thinking' => [
                'type' => $this->type,
                'data' => $this->data,
            ],
            'tool_use' => [
                'type' => $this->type,
                'id' => $this->id,
                'name' => $this->name,
                'input' => $this->input,
            ],
            default => [
                'type' => $this->type,
                'text' => $this->text,
            ],
        };
    }
}
