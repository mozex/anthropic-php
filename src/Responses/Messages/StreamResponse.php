<?php

namespace Anthropic\Responses\Messages;

use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Responses\Meta\MetaInformation;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @template TResponse
 *
 * @implements ResponseStreamContract<TResponse>
 */
final class StreamResponse implements ResponseHasMetaInformationContract, ResponseStreamContract
{
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
        while (! $this->response->getBody()->eof()) {
            $line = $this->readLine($this->response->getBody());

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, strlen('data:')));

            /** @var array{type: string, error?: array{type: ?string, message: string|array<int, string>}} $response */
            $response = json_decode($data, true, flags: JSON_THROW_ON_ERROR);

            if (isset($response['error'])) {
                throw new ErrorException($response['error'], $this->response->getStatusCode());
            }

            if ($response['type'] === 'message_stop') {
                break;
            }

            if (! in_array($response['type'], [
                'message_start',
                'message_delta',
                'content_block_delta',
            ])) {
                continue;
            }

            yield $this->responseClass::from($response);
        }
    }

    /**
     * Read a line from the stream.
     */
    private function readLine(StreamInterface $stream): string
    {
        $buffer = '';

        while (! $stream->eof()) {
            if ('' === ($byte = $stream->read(1))) {
                return $buffer;
            }
            $buffer .= $byte;
            if ($byte === "\n") {
                break;
            }
        }

        return $buffer;
    }

    public function meta(): MetaInformation
    {
        // @phpstan-ignore-next-line
        return MetaInformation::from($this->response->getHeaders());
    }
}
