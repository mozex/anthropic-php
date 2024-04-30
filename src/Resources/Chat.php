<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\ChatContract;
use Anthropic\Responses\Chat\CreateResponse;
use Anthropic\Responses\Chat\CreateStreamedResponse;
use Anthropic\Responses\StreamResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;

final class Chat implements ChatContract
{
    use Concerns\Streamable;
    use Concerns\Transportable;

    /**
     * Creates a completion for the chat message
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse
    {
        $this->ensureNotStreamed($parameters);

        $payload = Payload::create('chat/completions', $parameters);

        /** @var Response<array{id: string, object: string, created: int, model: string, system_fingerprint?: string, choices: array<int, array{index: int, message: array{role: string, content: ?string, function_call: ?array{name: string, arguments: string}, tool_calls: ?array<int, array{id: string, type: string, function: array{name: string, arguments: string}}>}, finish_reason: string|null}>, usage: array{prompt_tokens: int, completion_tokens: int|null, total_tokens: int}}> $response */
        $response = $this->transporter->requestObject($payload);

        return CreateResponse::from($response->data(), $response->meta());
    }

    /**
     * Creates a streamed completion for the chat message
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param  array<string, mixed>  $parameters
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function createStreamed(array $parameters): StreamResponse
    {
        $parameters = $this->setStreamParameter($parameters);

        $payload = Payload::create('chat/completions', $parameters);

        $response = $this->transporter->requestStream($payload);

        return new StreamResponse(CreateStreamedResponse::class, $response);
    }
}
