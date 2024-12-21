<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseContent
{
    /**
     * @param  array<string, string>|null  $input
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $text,
        public readonly ?string $id,
        public readonly ?string $name,
        public readonly ?array $input,
    ) {}

    /**
     * @param  array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, string>|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['text'] ?? null,
            $attributes['id'] ?? null,
            $attributes['name'] ?? null,
            $attributes['input'] ?? null,
        );
    }

    /**
     * @return array{type: string, text?: string|null, id?: string|null, name?: string|null, input?: array<string, string>|null}
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
        ];

        if (empty($this->input)) {
            $data['text'] = $this->text;

            return $data;
        }

        $data['id'] = $this->id;
        $data['name'] = $this->name;
        $data['input'] = $this->input;

        return $data;
    }
}
