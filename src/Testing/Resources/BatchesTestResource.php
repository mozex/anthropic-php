<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\BatchesContract;
use Anthropic\Resources\Batches;
use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class BatchesTestResource implements BatchesContract
{
    use Testable;

    protected function resource(): string
    {
        return Batches::class;
    }

    public function create(array $parameters): BatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $id): BatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(array $parameters = []): BatchListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function cancel(string $id): BatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $id): DeletedBatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
