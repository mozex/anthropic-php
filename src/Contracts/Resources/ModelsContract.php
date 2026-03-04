<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;

interface ModelsContract
{
    /**
     * Lists the currently available models.
     *
     * @see https://docs.anthropic.com/en/api/models-list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): ListResponse;

    /**
     * Gets information about a specific model.
     *
     * @see https://docs.anthropic.com/en/api/models-get
     */
    public function retrieve(string $model): RetrieveResponse;
}
