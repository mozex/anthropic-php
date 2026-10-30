<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\BatchesContract;
use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;
use Generator;

class Batches implements BatchesContract
{
    use Concerns\Paginates;
    use Concerns\Transportable;

    /**
     * Creates a Message Batch.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): BatchResponse
    {
        $payload = Payload::create('messages/batches', $parameters);

        /** @var Response<array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}> $response */
        $response = $this->transporter->requestObject($payload);

        return BatchResponse::from($response->data(), $response->meta());
    }

    /**
     * Retrieves a Message Batch.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/retrieve
     */
    public function retrieve(string $id): BatchResponse
    {
        $payload = Payload::retrieve('messages/batches', $id);

        /** @var Response<array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}> $response */
        $response = $this->transporter->requestObject($payload);

        return BatchResponse::from($response->data(), $response->meta());
    }

    /**
     * Lists Message Batches.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): BatchListResponse
    {
        $payload = Payload::list('messages/batches', $parameters);

        /** @var Response<array{data: array<int, array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}>, first_id?: string|null, last_id?: string|null, has_more: bool}> $response */
        $response = $this->transporter->requestObject($payload);

        return BatchListResponse::from($response->data(), $response->meta());
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

    /**
     * Cancels a Message Batch.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/cancel
     */
    public function cancel(string $id): BatchResponse
    {
        $payload = Payload::cancel('messages/batches', $id);

        /** @var Response<array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}> $response */
        $response = $this->transporter->requestObject($payload);

        return BatchResponse::from($response->data(), $response->meta());
    }

    /**
     * Deletes a Message Batch.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/delete
     */
    public function delete(string $id): DeletedBatchResponse
    {
        $payload = Payload::delete('messages/batches', $id);

        /** @var Response<array{id: string, type: string}> $response */
        $response = $this->transporter->requestObject($payload);

        return DeletedBatchResponse::from($response->data(), $response->meta());
    }

    /**
     * Retrieves Message Batch results.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/results
     */
    public function results(string $id): BatchResultResponse
    {
        $payload = Payload::retrieve('messages/batches', $id, '/results');

        $response = $this->transporter->requestStream($payload);

        return new BatchResultResponse($response);
    }
}
