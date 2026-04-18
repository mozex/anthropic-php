<?php

declare(strict_types=1);

namespace Anthropic\Responses\Files;

final class FileResponseScope
{
    private function __construct(
        public readonly string $id,
        public readonly string $type,
    ) {}

    /**
     * @param  array{id: string, type: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['id'],
            $attributes['type'],
        );
    }

    /**
     * @return array{id: string, type: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
        ];
    }
}
