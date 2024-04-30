<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\EmbeddingsContract;
use Anthropic\Resources\Embeddings;
use Anthropic\Responses\Embeddings\CreateResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class EmbeddingsTestResource implements EmbeddingsContract
{
    use Testable;

    protected function resource(): string
    {
        return Embeddings::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
