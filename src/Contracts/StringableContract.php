<?php

declare(strict_types=1);

namespace Anthropic\Contracts;

/**
 * @internal
 */
interface StringableContract
{
    /**
     * Returns the string representation of the object.
     */
    public function toString(): string;
}
