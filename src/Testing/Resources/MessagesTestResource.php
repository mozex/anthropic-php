<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\MessagesContract;
use Anthropic\Resources\Messages;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\StreamResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class MessagesTestResource implements MessagesContract
{
    use Testable;

    protected function resource(): string
    {
        return Messages::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function createStreamed(array $parameters): StreamResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
