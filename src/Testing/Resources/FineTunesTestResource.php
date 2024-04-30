<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\FineTunesContract;
use Anthropic\Resources\FineTunes;
use Anthropic\Responses\FineTunes\ListEventsResponse;
use Anthropic\Responses\FineTunes\ListResponse;
use Anthropic\Responses\FineTunes\RetrieveResponse;
use Anthropic\Responses\StreamResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class FineTunesTestResource implements FineTunesContract
{
    use Testable;

    protected function resource(): string
    {
        return FineTunes::class;
    }

    public function create(array $parameters): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(): ListResponse
    {
        return $this->record(__FUNCTION__);
    }

    public function retrieve(string $fineTuneId): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function cancel(string $fineTuneId): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function listEvents(string $fineTuneId): ListEventsResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function listEventsStreamed(string $fineTuneId): StreamResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
