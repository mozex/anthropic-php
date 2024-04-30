<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ImagesContract;
use Anthropic\Resources\Images;
use Anthropic\Responses\Images\CreateResponse;
use Anthropic\Responses\Images\EditResponse;
use Anthropic\Responses\Images\VariationResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ImagesTestResource implements ImagesContract
{
    use Testable;

    protected function resource(): string
    {
        return Images::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function edit(array $parameters): EditResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function variation(array $parameters): VariationResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
