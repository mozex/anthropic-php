<?php

declare(strict_types=1);

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\BatchesContract;
use Anthropic\Resources\Batches;
use Anthropic\Resources\Concerns\Paginates;
use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;
use Anthropic\Testing\Resources\Concerns\Testable;
use Generator;

class BatchesTestResource implements BatchesContract
{
    use Paginates;
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

    /**
     * Lists every Message Batch, page by page: the client follows the cursor until the last page and yields one
     * `BatchResponse` at a time. Nothing is requested until you loop over it.
     *
     * @param  array<string, mixed>  $parameters
     * @return Generator<int, BatchResponse, mixed, void>
     */
    public function iterate(array $parameters = []): Generator
    {
        return $this->paginate($parameters, $this->list(...), fn (BatchListResponse $page): array => $page->data);
    }

    public function cancel(string $id): BatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $id): DeletedBatchResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function results(string $id): BatchResultResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
