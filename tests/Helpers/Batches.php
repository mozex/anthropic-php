<?php

/**
 * @return array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}
 */
function batchResponse(): array
{
    return [
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

/**
 * @return array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}
 */
function batchInProgressResponse(): array
{
    return [
        'id' => 'msgbatch_07V2nm5PqB3bP8szLgTmn1EG',
        'type' => 'message_batch',
        'processing_status' => 'in_progress',
        'request_counts' => [
            'processing' => 50,
            'succeeded' => 0,
            'errored' => 0,
            'canceled' => 0,
            'expired' => 0,
        ],
        'created_at' => '2025-04-01T14:00:00Z',
        'expires_at' => '2025-04-02T14:00:00Z',
        'ended_at' => null,
        'cancel_initiated_at' => null,
        'archived_at' => null,
        'results_url' => null,
    ];
}

/**
 * @return array{data: array<int, array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}>, first_id: string, last_id: string, has_more: bool}
 */
function batchListResponse(): array
{
    return [
        'data' => [
            batchResponse(),
            batchInProgressResponse(),
        ],
        'first_id' => 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x',
        'last_id' => 'msgbatch_07V2nm5PqB3bP8szLgTmn1EG',
        'has_more' => false,
    ];
}

/**
 * @return array{id: string, type: string}
 */
function deletedBatchResponse(): array
{
    return [
        'id' => 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x',
        'type' => 'message_batch_deleted',
    ];
}

/**
 * @return array{custom_id: string, result: array{type: string, message: array<string, mixed>}}
 */
function batchIndividualSucceededResponse(): array
{
    return [
        'custom_id' => 'request-1',
        'result' => [
            'type' => 'succeeded',
            'message' => [
                'id' => 'msg_014VwiXbi91y3JMjcpyGBHX2',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello! How can I help you today?',
                    ],
                ],
                'model' => 'claude-sonnet-4-6',
                'stop_reason' => 'end_turn',
                'stop_sequence' => null,
                'usage' => [
                    'input_tokens' => 25,
                    'output_tokens' => 150,
                    'cache_creation_input_tokens' => 0,
                    'cache_read_input_tokens' => 0,
                ],
            ],
        ],
    ];
}

/**
 * @return array{custom_id: string, result: array{type: string, error: array{type: string, request_id: string, error: array{type: string, message: string}}}}
 */
function batchIndividualErroredResponse(): array
{
    return [
        'custom_id' => 'request-2',
        'result' => [
            'type' => 'errored',
            'error' => [
                'type' => 'error',
                'request_id' => 'req_abcdef123456',
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => 'max_tokens: Field required',
                ],
            ],
        ],
    ];
}

/**
 * @return array{custom_id: string, result: array{type: string}}
 */
function batchIndividualCanceledResponse(): array
{
    return [
        'custom_id' => 'request-3',
        'result' => [
            'type' => 'canceled',
        ],
    ];
}

/**
 * @return array{custom_id: string, result: array{type: string}}
 */
function batchIndividualExpiredResponse(): array
{
    return [
        'custom_id' => 'request-4',
        'result' => [
            'type' => 'expired',
        ],
    ];
}

/**
 * @return resource
 */
function batchResultsStream()
{
    return fopen(__DIR__.'/Streams/BatchResults.jsonl', 'r');
}
