<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Contracts\ResponseStreamContract;
use Anthropic\Responses\Concerns\ReadsStreams;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Batches\FakeableForBatchResultResponse;
use Generator;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements ResponseStreamContract<BatchIndividualResponse>
 */
class BatchResultResponse implements ResponseHasMetaInformationContract, ResponseStreamContract
{
    use FakeableForBatchResultResponse;
    use ReadsStreams;

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
        foreach ($this->readLines($this->response->getBody()) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            /** @var array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string, error: array{type: string, message: string}}}} $data */
            $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            yield BatchIndividualResponse::from($data);
        }
    }

    public function meta(): MetaInformation
    {
        return MetaInformation::from($this->response->getHeaders());
    }
}
