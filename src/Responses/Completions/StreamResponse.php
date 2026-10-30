<?php

declare(strict_types=1);

namespace Anthropic\Responses\Completions;

use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Responses\Concerns\ReadsStreams;
use Generator;
use Psr\Http\Message\ResponseInterface;

/**
 * @template TResponse
 *
 * @implements ResponseStreamContract<TResponse>
 */
class StreamResponse implements ResponseStreamContract
{
    use ReadsStreams;

    /**
     * Creates a new Stream Response instance.
     *
     * @param  class-string<TResponse>  $responseClass
     */
    public function __construct(
        private readonly string $responseClass,
        private readonly ResponseInterface $response,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Generator
    {
        foreach ($this->readEvents($this->response->getBody()) as $data) {
            /** @var array{type: string, error?: string|array{type: ?string, message: string|array<int, string>, details?: mixed}, request_id?: ?string} $response */
            $response = json_decode(trim($data), true, flags: JSON_THROW_ON_ERROR);

            if (isset($response['error'])) {
                $error = is_string($response['error']) ? ['message' => $response['error']] : $response['error'];

                if (is_string($response['request_id'] ?? null)) {
                    $error['request_id'] = $response['request_id'];
                }

                throw ErrorException::forType($error, $this->response);
            }

            if ($response['type'] !== 'completion') {
                continue;
            }

            yield $this->responseClass::from($response);
        }
    }
}
