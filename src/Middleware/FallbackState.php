<?php

declare(strict_types=1);

namespace Anthropic\Middleware;

/**
 * Which model of a fallback chain a sequence of requests (the turns of one conversation) is pinned to. Pass one to
 * `RefusalFallbackMiddleware`: it moves the pin when a model refuses, so later turns skip the models that refused.
 */
class FallbackState
{
    /**
     * The index of the chain entry the requests are pinned to, or null for the requested model itself.
     */
    public ?int $index = null;
}
