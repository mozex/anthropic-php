<?php

use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $result = BatchListResponse::from(batchListResponse(), meta());

    expect($result)
        ->toBeInstanceOf(BatchListResponse::class)
        ->data->toBeArray()->toHaveCount(2)
        ->data->each->toBeInstanceOf(BatchResponse::class)
        ->firstId->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->lastId->toBe('msgbatch_07V2nm5PqB3bP8szLgTmn1EG')
        ->hasMore->toBeFalse()
        ->meta()->toBeInstanceOf(MetaInformation::class);

    expect($result->data[0])
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->processingStatus->toBe('ended');

    expect($result->data[1])
        ->id->toBe('msgbatch_07V2nm5PqB3bP8szLgTmn1EG')
        ->processingStatus->toBe('in_progress');
});

test('as array accessible', function () {
    $result = BatchListResponse::from(batchListResponse(), meta());

    expect(isset($result['data']))->toBeTrue();

    expect($result['data'])->toBeArray()->toHaveCount(2);
});

test('to array', function () {
    $result = BatchListResponse::from(batchListResponse(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(batchListResponse());
});

test('fake', function () {
    $response = BatchListResponse::fake();

    expect($response)
        ->data->toBeArray()->toHaveCount(1)
        ->firstId->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->hasMore->toBeFalse();
});

test('fake with override', function () {
    $response = BatchListResponse::fake([
        'has_more' => true,
    ]);

    expect($response)
        ->hasMore->toBeTrue();
});
