<?php

namespace Anthropic\Testing\Responses\Fixtures\Models;

final class ListResponseFixture
{
    public const ATTRIBUTES = [
        'data' => [
            RetrieveResponseFixture::ATTRIBUTES,
        ],
        'first_id' => 'claude-sonnet-4-6-20250514',
        'last_id' => 'claude-sonnet-4-6-20250514',
        'has_more' => false,
    ];
}
