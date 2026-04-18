<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\FilesContract;
use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;

final class Files implements FilesContract
{
    use Concerns\Transportable;

    /**
     * The beta header required for every Files API request. Auto-injected on every method.
     */
    private const BETA = 'files-api-2025-04-14';

    /**
     * Uploads a file to be referenced in future API calls.
     *
     * @see https://platform.claude.com/docs/en/api/files-create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function upload(array $parameters): FileResponse
    {
        $payload = Payload::upload('files', $parameters)->withBetas([self::BETA]);

        /** @var Response<array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}> $response */
        $response = $this->transporter->requestObject($payload);

        return FileResponse::from($response->data(), $response->meta());
    }

    /**
     * Lists files belonging to the workspace of the API key.
     *
     * @see https://platform.claude.com/docs/en/api/files-list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): FileListResponse
    {
        $payload = Payload::list('files', $parameters)->withBetas([self::BETA]);

        /** @var Response<array{data: array<int, array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}>, first_id?: ?string, last_id?: ?string, has_more?: bool}> $response */
        $response = $this->transporter->requestObject($payload);

        return FileListResponse::from($response->data(), $response->meta());
    }

    /**
     * Retrieves metadata for a specific file.
     *
     * @see https://platform.claude.com/docs/en/api/files-metadata
     */
    public function retrieveMetadata(string $fileId): FileResponse
    {
        $payload = Payload::retrieve('files', $fileId)->withBetas([self::BETA]);

        /** @var Response<array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}> $response */
        $response = $this->transporter->requestObject($payload);

        return FileResponse::from($response->data(), $response->meta());
    }

    /**
     * Downloads the contents of a file created by skills or the code execution tool.
     *
     * @see https://platform.claude.com/docs/en/api/files-content
     */
    public function download(string $fileId): string
    {
        $payload = Payload::retrieveContent('files', $fileId)->withBetas([self::BETA]);

        return $this->transporter->requestContent($payload);
    }

    /**
     * Deletes a file.
     *
     * @see https://platform.claude.com/docs/en/api/files-delete
     */
    public function delete(string $fileId): DeletedFileResponse
    {
        $payload = Payload::delete('files', $fileId)->withBetas([self::BETA]);

        /** @var Response<array{id: string, type: string}> $response */
        $response = $this->transporter->requestObject($payload);

        return DeletedFileResponse::from($response->data(), $response->meta());
    }
}
