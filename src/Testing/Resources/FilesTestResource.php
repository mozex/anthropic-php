<?php

declare(strict_types=1);

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\FilesContract;
use Anthropic\Resources\Concerns\Paginates;
use Anthropic\Resources\Files;
use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Testing\Resources\Concerns\Testable;
use Generator;

class FilesTestResource implements FilesContract
{
    use Paginates;
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

    /**
     * Lists every file, page by page: the client follows the cursor until the last page and yields one
     * `FileResponse` at a time. Nothing is requested until you loop over it.
     *
     * @param  array<string, mixed>  $parameters
     * @return Generator<int, FileResponse, mixed, void>
     */
    public function iterate(array $parameters = []): Generator
    {
        return $this->paginate($parameters, $this->list(...), fn (FileListResponse $page): array => $page->data);
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
