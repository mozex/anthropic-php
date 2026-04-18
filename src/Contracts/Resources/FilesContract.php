<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;

interface FilesContract
{
    /**
     * Uploads a file to be referenced in future API calls.
     *
     * @see https://platform.claude.com/docs/en/api/files-create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function upload(array $parameters): FileResponse;

    /**
     * Lists files belonging to the workspace of the API key.
     *
     * @see https://platform.claude.com/docs/en/api/files-list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): FileListResponse;

    /**
     * Retrieves metadata for a specific file.
     *
     * @see https://platform.claude.com/docs/en/api/files-metadata
     */
    public function retrieveMetadata(string $fileId): FileResponse;

    /**
     * Downloads the contents of a file created by skills or the code execution tool.
     *
     * @see https://platform.claude.com/docs/en/api/files-content
     */
    public function download(string $fileId): string;

    /**
     * Deletes a file.
     *
     * @see https://platform.claude.com/docs/en/api/files-delete
     */
    public function delete(string $fileId): DeletedFileResponse;
}
