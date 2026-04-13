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
     * @see https://platform.claude.com/docs/en/api/messages/batches/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): BatchResponse;

    /**
     * Retrieves a Message Batch.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/retrieve
     */
    public function retrieve(string $id): BatchResponse;

    /**
     * Lists Message Batches.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): BatchListResponse;

    /**
     * Cancels a Message Batch.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/cancel
     */
    public function cancel(string $id): BatchResponse;

    /**
     * Deletes a Message Batch.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/delete
     */
    public function delete(string $id): DeletedBatchResponse;

    /**
     * Retrieves Message Batch results.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/results
     */
    public function results(string $id): BatchResultResponse;
}
