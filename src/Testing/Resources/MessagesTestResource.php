<?php

declare(strict_types=1);

namespace Anthropic\Testing\Resources;

use Anthropic\Contracts\Resources\MessagesContract;
use Anthropic\Resources\Concerns\CountsToFit;
use Anthropic\Resources\Messages;
use Anthropic\Responses\Messages\CountTokensResponse;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\StreamResponse;
use Anthropic\Testing\Resources\Concerns\Testable;

class MessagesTestResource implements MessagesContract
{
    use CountsToFit;
    use Testable;

    protected function resource(): string
    {
        return Messages::class;
    }

    public function create(array $parameters): CreateResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function createStreamed(array $parameters): StreamResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function countTokens(array $parameters): CountTokensResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
