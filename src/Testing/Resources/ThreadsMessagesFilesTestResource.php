<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ThreadsMessagesFilesContract;
use Anthropic\Resources\ThreadsMessagesFiles;
use Anthropic\Responses\Threads\Messages\Files\ThreadMessageFileListResponse;
use Anthropic\Responses\Threads\Messages\Files\ThreadMessageFileResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ThreadsMessagesFilesTestResource implements ThreadsMessagesFilesContract
{
    use Testable;

    public function resource(): string
    {
        return ThreadsMessagesFiles::class;
    }

    public function retrieve(string $threadId, string $messageId, string $fileId): ThreadMessageFileResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $threadId, string $messageId, array $parameters = []): ThreadMessageFileListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
