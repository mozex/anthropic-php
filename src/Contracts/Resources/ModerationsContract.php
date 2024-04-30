<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Moderations\CreateResponse;

interface ModerationsContract
{
    /**
     * Classifies if text violates Anthropic's Content Policy.
     *
     * @see https://platform.openai.com/docs/api-reference/moderations/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): CreateResponse;
}
