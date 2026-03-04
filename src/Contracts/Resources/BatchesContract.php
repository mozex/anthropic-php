<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;

interface BatchesContract
{
    /**
     * Creates a Message Batch.
     *
     * @see https://docs.anthropic.com/en/api/creating-message-batches
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): BatchResponse;

    /**
     * Retrieves a Message Batch.
     *
     * @see https://docs.anthropic.com/en/api/retrieving-message-batches
     */
    public function retrieve(string $id): BatchResponse;

    /**
     * Lists Message Batches.
     *
     * @see https://docs.anthropic.com/en/api/listing-message-batches
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): BatchListResponse;

    /**
     * Cancels a Message Batch.
     *
     * @see https://docs.anthropic.com/en/api/canceling-message-batches
     */
    public function cancel(string $id): BatchResponse;

    /**
     * Deletes a Message Batch.
     *
     * @see https://docs.anthropic.com/en/api/deleting-message-batches
     */
    public function delete(string $id): DeletedBatchResponse;

    /**
     * Retrieves Message Batch results.
     *
     * @see https://docs.anthropic.com/en/api/retrieving-message-batch-results
     */
    public function results(string $id): BatchResultResponse;
}
