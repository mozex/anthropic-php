<?php

declare(strict_types=1);

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

    /**
     * Whether these Messages parameters fit in `$limit` input tokens, as the API counts them (`count_tokens`, which
     * bills nothing). Only what `count_tokens` takes is sent, so `create()`'s parameters can be passed as they are.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function fits(array $parameters, int $limit): bool;

    /**
     * These parameters with the oldest turns of `messages` dropped until they fit in `$limit` input tokens; as they are
     * when they already fit. A cut only falls before a user turn without tool results, so every tool call keeps its
     * result and the conversation still starts with the user; the system prompt and the tools stay, while a `system`
     * message among the dropped turns goes with them. It counts a few times (the full request, then a binary search
     * over the cuts), and throws when even the last cut doesn't fit. Thinking blocks kept after a cut were made with
     * the dropped history in place, which Claude 5.1 models reject unless `thinking.block_binding` says to drop them.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function trimToFit(array $parameters, int $limit): array;
}
