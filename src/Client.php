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
use Anthropic\Resources\Raw;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\ValueObjects\ClientSettings;

class Client implements ClientContract
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
     * @deprecated The Text Completions API is deprecated. Use messages() instead.
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

    /**
     * Returns a client whose requests also carry these options: `headers`, `query`, `betas`, `api_version`,
     * `workspace_id`, `user_profile_id` and `max_retries`. The client it's called on is left unchanged.
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        // @phpstan-ignore new.static (users extend Client; a subclass keeps the constructor or overrides this)
        return new static($this->transporter->withOptions($options));
    }

    /**
     * What this client sends with every request (the base URI, the API version, the betas, the workspace, the retries
     * and the timeouts of a Guzzle client the factory built), never its key or token: for a diagnostic page or
     * command that shows what's in use.
     */
    public function settings(): ClientSettings
    {
        return $this->transporter->settings();
    }

    /**
     * Sends a request to any API path, for an endpoint the client has no method for yet: the parameters go in the
     * query for GET and DELETE and in a JSON body for POST and PUT. An error status throws as on every other method.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function request(string $method, string $path, array $parameters = []): RawResponse
    {
        return (new Raw($this->transporter))->request($method, $path, $parameters);
    }
}
