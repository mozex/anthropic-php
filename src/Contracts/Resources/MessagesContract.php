<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Messages\CountTokensResponse;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\StreamResponse;

interface MessagesContract
{
    /**
     * Creates a completion for structured list of input messages
     *
     * @see https://platform.claude.com/docs/en/api/messages/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse;

    /**
     * Creates a streamed completion for structured list of input messages
     *
     * @see https://platform.claude.com/docs/en/build-with-claude/streaming
     *
     * @param  array<string, mixed>  $parameters
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function createStreamed(array $parameters): StreamResponse;

    /**
     * Counts the number of tokens in a message
     *
     * @see https://platform.claude.com/docs/en/api/messages/count_tokens
     *
     * @param  array<string, mixed>  $parameters
     */
    public function countTokens(array $parameters): CountTokensResponse;
}
