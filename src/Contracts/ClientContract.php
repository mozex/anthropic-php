<?php

namespace Anthropic\Contracts;

use Anthropic\Contracts\Resources\BatchesContract;
use Anthropic\Contracts\Resources\CompletionsContract;
use Anthropic\Contracts\Resources\MessagesContract;
use Anthropic\Contracts\Resources\ModelsContract;

interface ClientContract
{
    /**
     * The Text Completions API is a legacy API. We recommend using the Messages API going forward.
     *
     * @see https://platform.claude.com/docs/en/api/completions/create
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
}
