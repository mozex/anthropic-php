<?php

use Anthropic\Responses\Batches\BatchResultError;

test('from', function () {
    $result = BatchResultError::from([
        'type' => 'invalid_request_error',
        'message' => 'max_tokens: Field required',
    ]);

    expect($result)
        ->toBeInstanceOf(BatchResultError::class)
        ->type->toBe('invalid_request_error')
        ->message->toBe('max_tokens: Field required');
});

test('to array', function () {
    $result = BatchResultError::from([
        'type' => 'invalid_request_error',
        'message' => 'max_tokens: Field required',
    ]);

    expect($result->toArray())
        ->toBe([
            'type' => 'invalid_request_error',
            'message' => 'max_tokens: Field required',
        ]);
});
