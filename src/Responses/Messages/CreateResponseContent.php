<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseContent
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $text,
    ) {
    }

    /**
     * @param  array{type: string, text: string|null}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['type'],
            $attributes['text'] ?? null,
        );
    }

    /**
     * @return array{type: string, text: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
        ];
    }
}
