<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\AssistantsContract;
use Anthropic\Resources\Assistants;
use Anthropic\Responses\Assistants\AssistantDeleteResponse;
use Anthropic\Responses\Assistants\AssistantListResponse;
use Anthropic\Responses\Assistants\AssistantResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class AssistantsTestResource implements AssistantsContract
{
    use Testable;

    public function resource(): string
    {
        return Assistants::class;
    }

    public function create(array $parameters): AssistantResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $id): AssistantResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function modify(string $id, array $parameters): AssistantResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $id): AssistantDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(array $parameters = []): AssistantListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function files(): AssistantsFilesTestResource
    {
        return new AssistantsFilesTestResource($this->fake);
    }
}
