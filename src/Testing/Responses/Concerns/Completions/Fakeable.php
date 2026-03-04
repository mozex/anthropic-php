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
    ): static {
        $class = str_replace('Anthropic\\Responses\\', 'Anthropic\\Testing\\Responses\\Fixtures\\', static::class).'Fixture';

        return static::from(
            self::buildAttributes($class::ATTRIBUTES, $override, $strategy),
        );
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private static function buildAttributes(array $original, array $override, OverrideStrategy $strategy = OverrideStrategy::Merge): array
    {
        return match ($strategy) {
            OverrideStrategy::Replace => array_replace($original, $override),
            OverrideStrategy::Merge => array_replace_recursive($original, $override),
        };
    }
}
