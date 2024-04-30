<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ModerationsContract;
use Anthropic\Resources\Moderations;
use Anthropic\Responses\Moderations\CreateResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ModerationsTestResource implements ModerationsContract
{
    use Testable;

    protected function resource(): string
    {
        return Moderations::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
