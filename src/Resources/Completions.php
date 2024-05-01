<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\CompletionsContract;
use Anthropic\Responses\Completions\CreateResponse;
use Anthropic\Responses\Completions\CreateStreamedResponse;
use Anthropic\Responses\Completions\StreamResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;

final class Completions implements CompletionsContract
{
    use Concerns\Streamable;
    use Concerns\Transportable;

    /**
     * Creates a completion for the provided prompt and parameters
     *
     * @see https://docs.anthropic.com/claude/reference/complete_post
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse
    {
        $this->ensureNotStreamed($parameters);

        $payload = Payload::create('complete', $parameters);

        /** @var Response<array{type: string, id: string, completion: string, stop_reason: string, model: string, stop: string, log_id: string}> $response */
        $response = $this->transporter->requestObject($payload);

        return CreateResponse::from($response->data());
    }

    /**
     * Creates a streamed completion for the provided prompt and parameters
     *
     * @see https://docs.anthropic.com/claude/reference/streaming
     *
     * @param  array<string, mixed>  $parameters
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function createStreamed(array $parameters): StreamResponse
    {
        $parameters = $this->setStreamParameter($parameters);

        $payload = Payload::create('complete', $parameters);

        $response = $this->transporter->requestStream($payload);

        return new StreamResponse(CreateStreamedResponse::class, $response);
    }
}
