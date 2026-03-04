<?php

use Anthropic\Responses\Batches\BatchIndividualResponse;
use Anthropic\Responses\Batches\BatchResult;

test('from succeeded', function () {
    $result = BatchIndividualResponse::from(batchIndividualSucceededResponse());

    expect($result)
        ->toBeInstanceOf(BatchIndividualResponse::class)
        ->customId->toBe('request-1')
        ->result->toBeInstanceOf(BatchResult::class)
        ->result->type->toBe('succeeded');
});

test('from errored', function () {
    $result = BatchIndividualResponse::from(batchIndividualErroredResponse());

    expect($result)
        ->toBeInstanceOf(BatchIndividualResponse::class)
        ->customId->toBe('request-2')
        ->result->toBeInstanceOf(BatchResult::class)
        ->result->type->toBe('errored');
});

test('from canceled', function () {
    $result = BatchIndividualResponse::from(batchIndividualCanceledResponse());

    expect($result)
        ->toBeInstanceOf(BatchIndividualResponse::class)
        ->customId->toBe('request-3')
        ->result->type->toBe('canceled');
});

test('from expired', function () {
    $result = BatchIndividualResponse::from(batchIndividualExpiredResponse());

    expect($result)
        ->toBeInstanceOf(BatchIndividualResponse::class)
        ->customId->toBe('request-4')
        ->result->type->toBe('expired');
});

test('to array from succeeded', function () {
    $result = BatchIndividualResponse::from(batchIndividualSucceededResponse());

    expect($result->toArray())
        ->toHaveKey('custom_id', 'request-1')
        ->toHaveKey('result')
        ->and($result->toArray()['result'])
        ->toHaveKey('type', 'succeeded')
        ->toHaveKey('message');
});

test('to array from errored', function () {
    $result = BatchIndividualResponse::from(batchIndividualErroredResponse());

    expect($result->toArray())
        ->toBe([
            'custom_id' => 'request-2',
            'result' => [
                'type' => 'errored',
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => 'max_tokens: Field required',
                ],
            ],
        ]);
});
