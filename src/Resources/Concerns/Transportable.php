<?php

declare(strict_types=1);

namespace Anthropic\Resources\Concerns;

use Anthropic\Contracts\TransporterContract;

trait Transportable
{
    public function __construct(private readonly TransporterContract $transporter)
    {
        // ..
    }
}
