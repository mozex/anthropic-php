<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Responses\Raw\RawResponse;
use Anthropic\ValueObjects\Transporter\Payload;

/**
 * Requests to any API path, for endpoints the client has no resource for yet. `Client::request()` sends them.
 */
class Raw
{
    use Concerns\Transportable;

    /**
     * Sends a request to the path below the API root (`skills` or `/v1/skills`), with the parameters in the query
     * for GET and DELETE and in a JSON body for POST and PUT. An error status throws as on every other method.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function request(string $method, string $path, array $parameters = []): RawResponse
    {
        $payload = Payload::raw($method, $path, $parameters);

        $response = $this->transporter->requestRaw($payload);

        return RawResponse::from($response->status(), $response->data(), $response->meta());
    }
}
