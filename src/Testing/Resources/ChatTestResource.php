<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ChatContract;
use Anthropic\Resources\Chat;
use Anthropic\Responses\Chat\CreateResponse;
use Anthropic\Responses\StreamResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ChatTestResource implements ChatContract
{
    use Testable;

    protected function resource(): string
    {
        return Chat::class;
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
