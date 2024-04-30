<?php

namespace Anthropic\Testing\Responses\Fixtures\Models;

final class ListResponseFixture
{
    public const ATTRIBUTES = [
        'object' => 'list',
        'data' => [
            RetrieveResponseFixture::ATTRIBUTES,
        ],
    ];
}
