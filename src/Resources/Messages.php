<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\MessagesContract;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\StreamResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;

final class Messages implements MessagesContract
{
    use Concerns\Streamable;
    use Concerns\Transportable;

    /**
     * Creates a completion for structured list of input messages
     *
     * @see https://docs.anthropic.com/claude/reference/messages_post
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse
    {
        $this->ensureNotStreamed($parameters);

        $payload = Payload::create('messages', $parameters);

        /** @var Response<array{id: string, type: string, role: string, model: string, stop_sequence: string|null, usage: array{input_tokens: int, output_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int}, content: array<int, array{type: string, text?: string|null, id?: string, name?: string, input?: array<string, string>}>, stop_reason: string}> $response */
        $response = $this->transporter->requestObject($payload);

        return CreateResponse::from($response->data(), $response->meta());
    }

    /**
     * Creates a streamed completion for structured list of input messages
     *
     * @see https://docs.anthropic.com/claude/reference/messages-streaming
     *
     * @param  array<string, mixed>  $parameters
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function createStreamed(array $parameters): StreamResponse
    {
        $parameters = $this->setStreamParameter($parameters);

        $payload = Payload::create('messages', $parameters);

        $response = $this->transporter->requestStream($payload);

        return new StreamResponse(CreateStreamedResponse::class, $response);
    }
}
