<?php

declare(strict_types=1);

namespace Anthropic\Responses\Messages;

use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Responses\Concerns\ReadsStreams;
use Anthropic\Responses\Meta\MetaInformation;
use Generator;
use Psr\Http\Message\ResponseInterface;

/**
 * @template TResponse
 *
 * @implements ResponseStreamContract<TResponse>
 */
class StreamResponse implements ResponseHasMetaInformationContract, ResponseStreamContract
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
            /** @var array{type: string, error?: array{type: ?string, message: string|array<int, string>}} $response */
            $response = json_decode(trim($data), true, flags: JSON_THROW_ON_ERROR);

            if (isset($response['error'])) {
                throw new ErrorException($response['error'], $this->response);
            }

            if ($response['type'] === 'message_stop') {
                break;
            }

            if ($response['type'] === 'ping') {
                continue;
            }

            yield $this->responseClass::from($response);
        }
    }

    public function meta(): MetaInformation
    {
        return MetaInformation::from($this->response->getHeaders());
    }
}
