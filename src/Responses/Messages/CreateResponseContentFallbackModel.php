<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

class CreateResponseContentFallbackModel
{
    /**
     * @param  array{model: string}  $attributes
     */
    private function __construct(
        public readonly string $model,
        protected readonly array $attributes,
    ) {}

    /**
     * @param  array{model: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['model'],
            $attributes,
        );
    }

    /**
     * @return array{model: string}
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'model' => $this->model,
        ]);
    }
}
