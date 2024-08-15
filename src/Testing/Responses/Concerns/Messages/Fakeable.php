<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Messages;

use Anthropic\Responses\Meta\MetaInformation;

trait Fakeable
{
    /**
     * @param  array<string, mixed>  $override
     */
    public static function fake(array $override = [], ?MetaInformation $meta = null): static
    {
        $class = str_replace('Responses\\', 'Testing\\Responses\\Fixtures\\', static::class).'Fixture';

        return static::from(
            self::buildAttributes($class::ATTRIBUTES, $override),
            $meta ?? self::fakeResponseMetaInformation(),
        );
    }

    /**
     * @return mixed[]
     */
    private static function buildAttributes(array $original, array $override): array
    {
        $new = [];

        foreach ($original as $key => $entry) {
            $new[$key] = is_array($entry) ?
                self::buildAttributes($entry, $override[$key] ?? []) :
                $override[$key] ?? $entry;
            unset($override[$key]);
        }

        // we are going to append all remaining overrides
        foreach ($override as $key => $value) {
            if (! is_numeric($key)) {
                continue;
            }

            $new[$key] = $value;
        }

        return $new;
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
