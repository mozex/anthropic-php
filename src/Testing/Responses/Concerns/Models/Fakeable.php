<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Models;

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Enums\OverrideStrategy;

trait Fakeable
{
    /**
     * @param  array<string, mixed>  $override
     */
    public static function fake(
        array $override = [],
        ?MetaInformation $meta = null,
        OverrideStrategy $strategy = OverrideStrategy::Merge,
    ): static {
        $class = str_replace('Anthropic\\Responses\\', 'Anthropic\\Testing\\Responses\\Fixtures\\', static::class).'Fixture';

        return static::from(
            self::buildAttributes($class::ATTRIBUTES, $override, $strategy),
            $meta ?? self::fakeResponseMetaInformation(),
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

    public static function fakeResponseMetaInformation(): MetaInformation
    {
        return MetaInformation::from([
            'anthropic-ratelimit-requests-limit' => [5],
            'anthropic-ratelimit-requests-remaining' => [4],
            'anthropic-ratelimit-requests-reset' => ['2024-04-30T15:56:17Z'],
            'anthropic-ratelimit-tokens-limit' => [25000],
            'anthropic-ratelimit-tokens-remaining' => [25000],
            'anthropic-ratelimit-tokens-reset' => ['2024-04-30T15:56:17Z'],
            'request-id' => ['02c10373f63cf2954851197d75c0adab'],
        ]);
    }
}
