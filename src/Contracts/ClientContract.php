<?php

declare(strict_types=1);

namespace Anthropic\Contracts;

use Anthropic\Contracts\Resources\BatchesContract;
use Anthropic\Contracts\Resources\CompletionsContract;
use Anthropic\Contracts\Resources\FilesContract;
use Anthropic\Contracts\Resources\MessagesContract;
use Anthropic\Contracts\Resources\ModelsContract;
use Anthropic\Responses\Raw\RawResponse;

interface ClientContract
{
    /**
     * The Text Completions API is a legacy API. We recommend using the Messages API going forward.
     *
     * @see https://platform.claude.com/docs/en/api/completions/create
     * @deprecated The Text Completions API is deprecated. Use messages() instead.
     */
    public function completions(): CompletionsContract;

    /**
     * Send a structured list of input messages with text and/or image content, and the model will
     * generate the next message in the conversation.
     *
     * @see https://platform.claude.com/docs/en/api/messages/create
     */
    public function messages(): MessagesContract;

    /**
     * List and retrieve information about available models.
     *
     * @see https://platform.claude.com/docs/en/api/models
     */
    public function models(): ModelsContract;

    /**
     * Create, retrieve, list, cancel, and delete Message Batches.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/create
     */
    public function batches(): BatchesContract;

    /**
     * Upload, list, retrieve, download, and delete files.
     *
     * @see https://platform.claude.com/docs/en/build-with-claude/files
     */
    public function files(): FilesContract;

    /**
     * Returns a client whose requests also carry these options: `headers`, `query`, `betas`, `api_version`,
     * `workspace_id`, `user_profile_id` and `max_retries`. The client it's called on is left unchanged.
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): ClientContract;

    /**
     * Sends a request to any API path, for an endpoint the client has no method for yet: the parameters go in the
     * query for GET and DELETE and in a JSON body for POST and PUT. An error status throws as on every other method.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function request(string $method, string $path, array $parameters = []): RawResponse;
}
