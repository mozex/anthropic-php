<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateStreamedResponseContentBlockStart
{
    /**
     * @param  array<int, string>  $input
     */
    private function __construct(
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $text,
        public readonly ?string $name,
        public readonly ?array $input,
    ) {}

    /**
     * @param  array{id?: string, type?: string, text?: string, name?: string, input?: array<int, string>}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['id'] ?? null,
            $attributes['type'] ?? null,
            $attributes['text'] ?? null,
            $attributes['name'] ?? null,
            $attributes['input'] ?? null,
        );
    }

    /**
     * @return array{id: string|null, type: string|null, text: string|null, name: string|null, input: array<int, string>|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'text' => $this->text,
            'name' => $this->name,
            'input' => $this->input,
        ];
    }
}
