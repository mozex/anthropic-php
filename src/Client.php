<?php

declare(strict_types=1);

namespace Anthropic;

use Anthropic\Contracts\ClientContract;
use Anthropic\Contracts\TransporterContract;
use Anthropic\Resources\Completions;
use Anthropic\Resources\Messages;

final class Client implements ClientContract
{
    /**
     * Creates a Client instance with the given API token.
     */
    public function __construct(private readonly TransporterContract $transporter)
    {
        // ..
    }

    /**
     * The Text Completions API is a legacy API. We recommend using the Messages API going forward.
     *
     * @see https://docs.anthropic.com/claude/reference/complete_post
     */
    public function completions(): Completions
    {
        return new Completions($this->transporter);
    }

    /**
     * Send a structured list of input messages with text and/or image content, and the model will
     * generate the next message in the conversation.
     *
     * @see https://docs.anthropic.com/claude/reference/messages_post
     */
    public function messages(): Messages
    {
        return new Messages($this->transporter);
    }
}
