<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ThreadsRunsStepsContract;
use Anthropic\Resources\ThreadsRunsSteps;
use Anthropic\Responses\Threads\Runs\Steps\ThreadRunStepListResponse;
use Anthropic\Responses\Threads\Runs\Steps\ThreadRunStepResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

class ThreadsRunsStepsTestResource implements ThreadsRunsStepsContract
{
    use Testable;

    public function resource(): string
    {
        return ThreadsRunsSteps::class;
    }

    public function retrieve(string $threadId, string $runId, string $stepId): ThreadRunStepResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $threadId, string $runId, array $parameters = []): ThreadRunStepListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
