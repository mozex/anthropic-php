<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

final class CreateResponseContentFallbackModel
{
    private function __construct(
        public readonly string $model,
    ) {}

    /**
     * @param  array{model: string}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            $attributes['model'],
        );
    }

    /**
     * @return array{model: string}
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
        ];
    }
}
