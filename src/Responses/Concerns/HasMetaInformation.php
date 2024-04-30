<?php

declare(strict_types=1);

namespace Anthropic\Responses\Concerns;

use Anthropic\Responses\Meta\MetaInformation;

trait HasMetaInformation
{
    public function meta(): MetaInformation
    {
        return $this->meta;
    }
}
