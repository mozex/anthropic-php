<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\EditsContract;
use Anthropic\Resources\Edits;
use Anthropic\Responses\Edits\CreateResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class EditsTestResource implements EditsContract
{
    use Testable;

    protected function resource(): string
    {
        return Edits::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
