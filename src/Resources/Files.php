<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\FilesContract;
use Anthropic\Responses\Files\CreateResponse;
use Anthropic\Responses\Files\DeleteResponse;
use Anthropic\Responses\Files\ListResponse;
use Anthropic\Responses\Files\RetrieveResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;

final class Files implements FilesContract
{
    use Concerns\Transportable;

    /**
     * Returns a list of files that belong to the user's organization.
     *
     * @see https://platform.openai.com/docs/api-reference/files/list
     */
    public function list(): ListResponse
    {
        $payload = Payload::list('files');

        /** @var Response<array{object: string, data: array<int, array{id: string, object: string, created_at: int, bytes: ?int, filename: string, purpose: string, status: string, status_details: array<array-key, mixed>|string|null}>}> $response */
        $response = $this->transporter->requestObject($payload);

        return ListResponse::from($response->data(), $response->meta());
    }

    /**
     * Returns information about a specific file.
     *
     * @see https://platform.openai.com/docs/api-reference/files/retrieve
     */
    public function retrieve(string $file): RetrieveResponse
    {
        $payload = Payload::retrieve('files', $file);

        /** @var Response<array{id: string, object: string, created_at: int, bytes: ?int, filename: string, purpose: string, status: string, status_details: array<array-key, mixed>|string|null}> $response */
        $response = $this->transporter->requestObject($payload);

        return RetrieveResponse::from($response->data(), $response->meta());
    }

    /**
     * Returns the contents of the specified file.
     *
     * @see https://platform.openai.com/docs/api-reference/files/retrieve-content
     */
    public function download(string $file): string
    {
        $payload = Payload::retrieveContent('files', $file);

        return $this->transporter->requestContent($payload);
    }

    /**
     * Upload a file that contains document(s) to be used across various endpoints/features.
     *
     * @see https://platform.openai.com/docs/api-reference/files/upload
     *
     * @param  array<string, mixed>  $parameters
     */
    public function upload(array $parameters): CreateResponse
    {
        $payload = Payload::upload('files', $parameters);

        /** @var Response<array{id: string, object: string, created_at: int, bytes: int, filename: string, purpose: string, status: string, status_details: array<array-key, mixed>|string|null}> $response */
        $response = $this->transporter->requestObject($payload);

        return CreateResponse::from($response->data(), $response->meta());
    }

    /**
     * Delete a file.
     *
     * @see https://platform.openai.com/docs/api-reference/files/delete
     */
    public function delete(string $file): DeleteResponse
    {
        $payload = Payload::delete('files', $file);

        /** @var Response<array{id: string, object: string, deleted: bool}> $response */
        $response = $this->transporter->requestObject($payload);

        return DeleteResponse::from($response->data(), $response->meta());
    }
}
