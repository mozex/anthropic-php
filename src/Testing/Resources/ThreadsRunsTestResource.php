<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ThreadsRunsContract;
use Anthropic\Resources\ThreadsRuns;
use Anthropic\Responses\Threads\Runs\ThreadRunListResponse;
use Anthropic\Responses\Threads\Runs\ThreadRunResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ThreadsRunsTestResource implements ThreadsRunsContract
{
    use Testable;

    public function resource(): string
    {
        return ThreadsRuns::class;
    }

    public function create(string $threadId, array $parameters): ThreadRunResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $threadId, string $runId): ThreadRunResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function modify(string $threadId, string $runId, array $parameters): ThreadRunResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function submitToolOutputs(string $threadId, string $runId, array $parameters): ThreadRunResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function cancel(string $threadId, string $runId): ThreadRunResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $threadId, array $parameters = []): ThreadRunListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function steps(): ThreadsRunsStepsTestResource
    {
        return new ThreadsRunsStepsTestResource($this->fake);
    }
}
