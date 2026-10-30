<?php

declare(strict_types=1);

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\ModelsContract;
use Anthropic\Resources\Concerns\Paginates;
use Anthropic\Resources\Models;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\Resources\Concerns\Testable;
use Generator;

class ModelsTestResource implements ModelsContract
{
    use Paginates;
    use Testable;

    protected function resource(): string
    {
        return Models::class;
    }

    public function list(array $parameters = []): ListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    /**
     * Lists every model, page by page: the client follows the cursor until the last page and yields one
     * `RetrieveResponse` at a time. Nothing is requested until you loop over it.
     *
     * @param  array<string, mixed>  $parameters
     * @return Generator<int, RetrieveResponse, mixed, void>
     */
    public function iterate(array $parameters = []): Generator
    {
        return $this->paginate($parameters, $this->list(...), fn (ListResponse $page): array => $page->data);
    }

    public function retrieve(string $model): RetrieveResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
