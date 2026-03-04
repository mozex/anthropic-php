<?php

namespace Anthropic\Testing\Responses\Fixtures\Batches;

final class BatchResponseFixture
{
    public const ATTRIBUTES = [
        'id' => 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x',
        'type' => 'message_batch',
        'processing_status' => 'ended',
        'request_counts' => [
            'processing' => 0,
            'succeeded' => 95,
            'errored' => 3,
            'canceled' => 1,
            'expired' => 1,
        ],
        'created_at' => '2025-04-01T12:00:00Z',
        'expires_at' => '2025-04-02T12:00:00Z',
        'ended_at' => '2025-04-01T12:30:00Z',
        'cancel_initiated_at' => null,
        'archived_at' => null,
        'results_url' => 'https://api.anthropic.com/v1/messages/batches/msgbatch_04Rka1yCsMLGPnR7kfPdgR8x/results',
    ];
}
