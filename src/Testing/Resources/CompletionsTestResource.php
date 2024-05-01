<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\CompletionsContract;
use Anthropic\Resources\Completions;
use Anthropic\Responses\Completions\CreateResponse;
use Anthropic\Responses\Completions\StreamResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class CompletionsTestResource implements CompletionsContract
{
    use Testable;

    protected function resource(): string
    {
        return Completions::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function createStreamed(array $parameters): StreamResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
