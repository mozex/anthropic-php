<?php

declare(strict_types=1);

namespace Anthropic\Testing\Enums;

enum OverrideStrategy
{
    case Merge;
    case Replace;

    /**
     * Applies a fake's overrides to its fixture attributes.
     *
     * Merge works like `array_replace_recursive()`, except that a nested item whose `type` differs
     * from the fixture's replaces it whole, so a faked `tool_use` block doesn't keep a fixture
     * `text` block's fields.
     *
     * @internal
     *
     * @param  array<array-key, mixed>  $original
     * @param  array<array-key, mixed>  $override
     * @return array<array-key, mixed>
     */
    public function apply(array $original, array $override): array
    {
        if ($this === self::Replace) {
            return array_replace($original, $override);
        }

        foreach ($override as $key => $value) {
            $current = $original[$key] ?? null;

            $merges = is_array($value) && is_array($current)
                && ! (isset($value['type'], $current['type']) && $value['type'] !== $current['type']);

            $original[$key] = $merges ? $this->apply($current, $value) : $value;
        }

        return $original;
    }
}
