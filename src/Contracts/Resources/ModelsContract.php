<?php

declare(strict_types=1);

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Generator;

interface ModelsContract
{
    /**
     * Lists the currently available models.
     *
     * @see https://platform.claude.com/docs/en/api/models/list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): ListResponse;

    /**
     * Lists every model, page by page: the client follows the cursor until the last page and yields one
     * `RetrieveResponse` at a time. Nothing is requested until you loop over it.
     *
     * @param  array<string, mixed>  $parameters
     * @return Generator<int, RetrieveResponse, mixed, void>
     */
    public function iterate(array $parameters = []): Generator;

    /**
     * Gets information about a specific model.
     *
     * @see https://platform.claude.com/docs/en/api/models/retrieve
     */
    public function retrieve(string $model): RetrieveResponse;
}
