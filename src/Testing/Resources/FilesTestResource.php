<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\FilesContract;
use Anthropic\Resources\Files;
use Anthropic\Responses\Files\CreateResponse;
use Anthropic\Responses\Files\DeleteResponse;
use Anthropic\Responses\Files\ListResponse;
use Anthropic\Responses\Files\RetrieveResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class FilesTestResource implements FilesContract
{
    use Testable;

    protected function resource(): string
    {
        return Files::class;
    }

    public function list(): ListResponse
    {
        return $this->record(__FUNCTION__);
    }

    public function retrieve(string $file): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function download(string $file): string
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function upload(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $file): DeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
