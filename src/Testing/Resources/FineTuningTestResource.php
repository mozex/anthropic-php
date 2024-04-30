<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\FineTuningContract;
use Anthropic\Resources\FineTuning;
use Anthropic\Responses\FineTuning\ListJobEventsResponse;
use Anthropic\Responses\FineTuning\ListJobsResponse;
use Anthropic\Responses\FineTuning\RetrieveJobResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class FineTuningTestResource implements FineTuningContract
{
    use Testable;

    protected function resource(): string
    {
        return FineTuning::class;
    }

    public function createJob(array $parameters): RetrieveJobResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function listJobs(array $parameters = []): ListJobsResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieveJob(string $jobId): RetrieveJobResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function cancelJob(string $jobId): RetrieveJobResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function listJobEvents(string $jobId, array $parameters = []): ListJobEventsResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
