<?php

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ModelsContract;
use Anthropic\Resources\Models;
use Anthropic\Responses\Models\DeleteResponse;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

final class ModelsTestResource implements ModelsContract
{
    use Testable;

    protected function resource(): string
    {
        return Models::class;
    }

    public function list(): ListResponse
    {
        return $this->record(__FUNCTION__);
    }

    public function retrieve(string $model): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $model): DeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
