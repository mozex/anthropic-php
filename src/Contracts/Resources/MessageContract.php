<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Chat\CreateResponse;
use Anthropic\Responses\Chat\CreateStreamedResponse;
use Anthropic\Responses\StreamResponse;

interface MessageContract
{
    /**
     * Creates a completion for the chat message
     *
     * @see https://docs.anthropic.com/claude/reference/messages_post
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse;

    /**
     * Creates a streamed completion for the chat message
     *
     * @see https://docs.anthropic.com/claude/reference/messages-streaming
     *
     * @param  array<string, mixed>  $parameters
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function createStreamed(array $parameters): StreamResponse;
}
