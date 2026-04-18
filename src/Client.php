<?php

declare(strict_types=1);

namespace Anthropic;

use Anthropic\Contracts\ClientContract;
use Anthropic\Contracts\TransporterContract;
use Anthropic\Resources\Batches;
use Anthropic\Resources\Completions;
use Anthropic\Resources\Files;
use Anthropic\Resources\Messages;
use Anthropic\Resources\Models;

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
     * @see https://platform.claude.com/docs/en/api/completions/create
     */
    public function completions(): Completions
    {
        return new Completions($this->transporter);
    }

    /**
     * Send a structured list of input messages with text and/or image content, and the model will
     * generate the next message in the conversation.
     *
     * @see https://platform.claude.com/docs/en/api/messages/create
     */
    public function messages(): Messages
    {
        return new Messages($this->transporter);
    }

    /**
     * List and retrieve information about available models.
     *
     * @see https://platform.claude.com/docs/en/api/models
     */
    public function models(): Models
    {
        return new Models($this->transporter);
    }

    /**
     * Create, retrieve, list, cancel, and delete Message Batches.
     *
     * @see https://platform.claude.com/docs/en/api/messages/batches/create
     */
    public function batches(): Batches
    {
        return new Batches($this->transporter);
    }

    /**
     * Upload, list, retrieve, download, and delete files.
     *
     * @see https://platform.claude.com/docs/en/build-with-claude/files
     */
    public function files(): Files
    {
        return new Files($this->transporter);
    }
}
