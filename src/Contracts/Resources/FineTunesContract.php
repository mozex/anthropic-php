<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\FineTunes\ListEventsResponse;
use Anthropic\Responses\FineTunes\ListResponse;
use Anthropic\Responses\FineTunes\RetrieveResponse;
use Anthropic\Responses\FineTunes\RetrieveStreamedResponseEvent;
use Anthropic\Responses\StreamResponse;

interface FineTunesContract
{
    /**
     * Creates a job that fine-tunes a specified model from a given dataset.
     *
     * Response includes details of the enqueued job including job status and the name of the fine-tuned models once complete.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): RetrieveResponse;

    /**
     * List your organization's fine-tuning jobs.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes/list
     */
    public function list(): ListResponse;

    /**
     * Gets info about the fine-tune job.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes/list
     */
    public function retrieve(string $fineTuneId): RetrieveResponse;

    /**
     * Immediately cancel a fine-tune job.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes/cancel
     */
    public function cancel(string $fineTuneId): RetrieveResponse;

    /**
     * Get fine-grained status updates for a fine-tune job.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes/events
     */
    public function listEvents(string $fineTuneId): ListEventsResponse;

    /**
     * Get streamed fine-grained status updates for a fine-tune job.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes/events
     *
     * @return StreamResponse<RetrieveStreamedResponseEvent>
     */
    public function listEventsStreamed(string $fineTuneId): StreamResponse;
}
