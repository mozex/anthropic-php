<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ThreadsContract;
use Anthropic\Resources\Threads;
use Anthropic\Responses\Threads\Runs\ThreadRunResponse;
use Anthropic\Responses\Threads\ThreadDeleteResponse;
use Anthropic\Responses\Threads\ThreadResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ThreadsTestResource implements ThreadsContract
{
    use Testable;

    public function resource(): string
    {
        return Threads::class;
    }

    public function create(array $parameters): ThreadResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function createAndRun(array $parameters): ThreadRunResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $id): ThreadResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function modify(string $id, array $parameters): ThreadResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $id): ThreadDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function messages(): ThreadsMessagesTestResource
    {
        return new ThreadsMessagesTestResource($this->fake);
    }

    public function runs(): ThreadsRunsTestResource
    {
        return new ThreadsRunsTestResource($this->fake);
    }
}
