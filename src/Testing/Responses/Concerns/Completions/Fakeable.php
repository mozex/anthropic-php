<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Completions;

use Anthropic\Testing\Enums\OverrideStrategy;

trait Fakeable
{
    /**
     * @param  array<string, mixed>  $override
     */
    public static function fake(
        array $override = [],
        OverrideStrategy $strategy = OverrideStrategy::Merge,
    ): self {
        $class = str_replace('Anthropic\\Responses\\', 'Anthropic\\Testing\\Responses\\Fixtures\\', self::class).'Fixture';

        return self::from(
            $strategy->apply($class::ATTRIBUTES, $override),
        );
    }
}
