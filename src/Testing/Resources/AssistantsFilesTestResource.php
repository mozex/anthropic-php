<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\AssistantsFilesContract;
use Anthropic\Resources\AssistantsFiles;
use Anthropic\Responses\Assistants\Files\AssistantFileDeleteResponse;
use Anthropic\Responses\Assistants\Files\AssistantFileListResponse;
use Anthropic\Responses\Assistants\Files\AssistantFileResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class AssistantsFilesTestResource implements AssistantsFilesContract
{
    use Testable;

    public function resource(): string
    {
        return AssistantsFiles::class;
    }

    public function create(string $assistantId, array $parameters): AssistantFileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $assistantId, string $fileId): AssistantFileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $assistantId, string $fileId): AssistantFileDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $assistantId, array $parameters = []): AssistantFileListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
