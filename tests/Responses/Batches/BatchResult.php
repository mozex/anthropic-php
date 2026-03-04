<?php

use Anthropic\Responses\Batches\BatchResult;
use Anthropic\Responses\Batches\BatchResultError;
use Anthropic\Responses\Messages\CreateResponse;

test('from succeeded', function () {
    $data = batchIndividualSucceededResponse();
    $result = BatchResult::from($data['result']);

    expect($result)
        ->toBeInstanceOf(BatchResult::class)
        ->type->toBe('succeeded')
        ->message->toBeInstanceOf(CreateResponse::class)
        ->message->id->toBe('msg_014VwiXbi91y3JMjcpyGBHX2')
        ->message->stop_reason->toBe('end_turn')
        ->error->toBeNull();
});

test('from errored', function () {
    $data = batchIndividualErroredResponse();
    $result = BatchResult::from($data['result']);

    expect($result)
        ->toBeInstanceOf(BatchResult::class)
        ->type->toBe('errored')
        ->message->toBeNull()
        ->error->toBeInstanceOf(BatchResultError::class)
        ->error->type->toBe('invalid_request_error')
        ->error->message->toBe('max_tokens: Field required');
});

test('from canceled', function () {
    $data = batchIndividualCanceledResponse();
    $result = BatchResult::from($data['result']);

    expect($result)
        ->toBeInstanceOf(BatchResult::class)
        ->type->toBe('canceled')
        ->message->toBeNull()
        ->error->toBeNull();
});

test('from expired', function () {
    $data = batchIndividualExpiredResponse();
    $result = BatchResult::from($data['result']);

    expect($result)
        ->toBeInstanceOf(BatchResult::class)
        ->type->toBe('expired')
        ->message->toBeNull()
        ->error->toBeNull();
});

test('to array from succeeded', function () {
    $data = batchIndividualSucceededResponse();
    $result = BatchResult::from($data['result']);

    expect($result->toArray())
        ->toHaveKey('type', 'succeeded')
        ->toHaveKey('message')
        ->not->toHaveKey('error');
});

test('to array from errored', function () {
    $data = batchIndividualErroredResponse();
    $result = BatchResult::from($data['result']);

    expect($result->toArray())
        ->toBe([
            'type' => 'errored',
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'max_tokens: Field required',
            ],
        ]);
});

test('to array from canceled', function () {
    $data = batchIndividualCanceledResponse();
    $result = BatchResult::from($data['result']);

    expect($result->toArray())
        ->toBe([
            'type' => 'canceled',
        ]);
});

test('to array from expired', function () {
    $data = batchIndividualExpiredResponse();
    $result = BatchResult::from($data['result']);

    expect($result->toArray())
        ->toBe([
            'type' => 'expired',
        ]);
});
