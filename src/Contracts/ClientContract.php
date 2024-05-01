<?php

namespace Anthropic\Contracts;

use Anthropic\Contracts\Resources\CompletionsContract;
use Anthropic\Contracts\Resources\MessagesContract;

interface ClientContract
{
    /**
     * The Text Completions API is a legacy API. We recommend using the Messages API going forward.
     *
     * @see https://docs.anthropic.com/claude/reference/complete_post
     */
    public function completions(): CompletionsContract;

    /**
     * Send a structured list of input messages with text and/or image content, and the model will
     * generate the next message in the conversation.
     *
     * @see https://docs.anthropic.com/claude/reference/messages_post
     */
    public function messages(): MessagesContract;
}
