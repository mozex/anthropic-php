<?php

namespace Anthropic\Testing\Responses\Fixtures\Models;

final class ListResponseFixture
{
    public const ATTRIBUTES = [
        'data' => [
            RetrieveResponseFixture::ATTRIBUTES,
        ],
        'first_id' => 'claude-sonnet-4-6',
        'last_id' => 'claude-sonnet-4-6',
        'has_more' => false,
    ];
}
