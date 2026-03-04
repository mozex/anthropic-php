<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Batches\FakeableForBatchResultResponse;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @implements ResponseStreamContract<BatchIndividualResponse>
 */
final class BatchResultResponse implements ResponseHasMetaInformationContract, ResponseStreamContract
{
    use FakeableForBatchResultResponse;

    /**
     * Creates a new Batch Result Response instance.
     */
    public function __construct(
        private readonly ResponseInterface $response,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Generator
    {
        while (! $this->response->getBody()->eof()) {
            $line = $this->readLine($this->response->getBody());

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            /** @var array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string, error: array{type: string, message: string}}}} $data */
            $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            yield BatchIndividualResponse::from($data);
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
        return MetaInformation::from($this->response->getHeaders());
    }
}
