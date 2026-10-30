<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Fixtures\Batches;

class BatchListResponseFixture
{
    public const ATTRIBUTES = [
        'data' => [
            BatchResponseFixture::ATTRIBUTES,
        ],
        'first_id' => 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x',
        'last_id' => 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x',
        'has_more' => false,
    ];
}
