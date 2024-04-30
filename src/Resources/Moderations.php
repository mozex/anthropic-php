<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\ModerationsContract;
use Anthropic\Responses\Moderations\CreateResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;

final class Moderations implements ModerationsContract
{
    use Concerns\Transportable;

    /**
     * Classifies if text violates Anthropic's Content Policy.
     *
     * @see https://platform.openai.com/docs/api-reference/moderations/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse
    {
        $payload = Payload::create('moderations', $parameters);

        /** @var Response<array{id: string, model: string, results: array<int, array{categories: array<string, bool>, category_scores: array<string, float>, flagged: bool}>}> $response */
        $response = $this->transporter->requestObject($payload);

        return CreateResponse::from($response->data(), $response->meta());
    }
}
