<?php

declare(strict_types=1);

namespace Anthropic\Responses\Files;

class FileResponseScope
{
    /**
     * @param  array{id: string, type: string}  $attributes
     */
    private function __construct(
        public readonly string $id,
        public readonly string $type,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{id: string, type: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['id'],
            $attributes['type'],
            $attributes,
        );
    }

    /**
     * @return array{id: string, type: string}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'id' => $this->id,
            'type' => $this->type,
        ]);
    }
}
