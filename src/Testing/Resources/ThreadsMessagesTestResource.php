<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ThreadsMessagesContract;
use Anthropic\Resources\ThreadsMessages;
use Anthropic\Responses\Threads\Messages\ThreadMessageListResponse;
use Anthropic\Responses\Threads\Messages\ThreadMessageResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ThreadsMessagesTestResource implements ThreadsMessagesContract
{
    use Testable;

    public function resource(): string
    {
        return ThreadsMessages::class;
    }

    public function create(string $threadId, array $parameters): ThreadMessageResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $threadId, string $messageId): ThreadMessageResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function modify(string $threadId, string $messageId, array $parameters): ThreadMessageResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $threadId, array $parameters = []): ThreadMessageListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function files(): ThreadsMessagesFilesTestResource
    {
        return new ThreadsMessagesFilesTestResource($this->fake);
    }
}
