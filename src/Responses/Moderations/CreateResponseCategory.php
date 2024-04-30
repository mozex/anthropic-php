<?php

declare(strict_types=1);

namespace Anthropic\Responses\Moderations;

use Anthropic\Enums\Moderations\Category;

final class CreateResponseCategory
{
    private function __construct(
        public readonly Category $category,
        public readonly bool $violated,
        public readonly float $score,
    ) {
    }

    /**
     * @param  array{category: string, violated: bool, score: float}  $attributes
     */
    public static function from(array $attributes): self
    {
        return new self(
            Category::from($attributes['category']),
            $attributes['violated'],
            $attributes['score'],
        );
    }
}
