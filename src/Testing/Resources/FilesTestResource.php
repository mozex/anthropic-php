<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\FilesContract;
use Anthropic\Resources\Files;
use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class FilesTestResource implements FilesContract
{
    use Testable;

    protected function resource(): string
    {
        return Files::class;
    }

    public function upload(array $parameters): FileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(array $parameters = []): FileListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieveMetadata(string $fileId): FileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function download(string $fileId): string
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $fileId): DeletedFileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
