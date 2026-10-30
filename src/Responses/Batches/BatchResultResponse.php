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
     *
     * @return Generator<int, BatchIndividualResponse>
     */
    public function getIterator(): Generator
    {
        foreach ($this->readLines($this->response->getBody()) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            /** @var array{custom_id: string, result: array{type: string, message?: array<string, mixed>, error?: array{type: string, request_id?: string|null, error: array{type: string, message: string}}|array{type: string, message: string, request_id?: string|null}}} $data */
            $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            yield BatchIndividualResponse::from($data);
        }
    }

    /**
     * @return Generator<int, BatchIndividualResponse>
     */
    private function ofType(string $type): Generator
    {
        foreach ($this->getIterator() as $individual) {
            if ($individual->result->type === $type) {
                yield $individual;
            }
        }
    }

    /**
     * The results that succeeded. Like every filter here, it reads the results as it goes, so a second loop over this
     * response, through any filter, continues where the first stopped: fetch the results again for another type.
     *
     * @return Generator<int, BatchIndividualResponse>
     */
    public function succeeded(): Generator
    {
        return $this->ofType('succeeded');
    }

    /**
     * The results that errored, each with its `error`.
     *
     * @return Generator<int, BatchIndividualResponse>
     */
    public function errored(): Generator
    {
        return $this->ofType('errored');
    }

    /**
     * The results of requests canceled before they ran.
     *
     * @return Generator<int, BatchIndividualResponse>
     */
    public function canceled(): Generator
    {
        return $this->ofType('canceled');
    }

    /**
     * The results of requests the batch's 24 hours ran out on.
     *
     * @return Generator<int, BatchIndividualResponse>
     */
    public function expired(): Generator
    {
        return $this->ofType('expired');
    }

    public function meta(): MetaInformation
    {
        return MetaInformation::from($this->response->getHeaders());
    }
}
