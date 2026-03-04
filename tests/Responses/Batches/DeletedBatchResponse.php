<?php

use Anthropic\Responses\Batches\DeletedBatchResponse;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $result = DeletedBatchResponse::from(deletedBatchResponse(), meta());

    expect($result)
        ->toBeInstanceOf(DeletedBatchResponse::class)
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->type->toBe('message_batch_deleted')
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('as array accessible', function () {
    $result = DeletedBatchResponse::from(deletedBatchResponse(), meta());

    expect(isset($result['id']))->toBeTrue();

    expect($result['id'])->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');
});

test('to array', function () {
    $result = DeletedBatchResponse::from(deletedBatchResponse(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(deletedBatchResponse());
});

test('fake', function () {
    $response = DeletedBatchResponse::fake();

    expect($response)
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->type->toBe('message_batch_deleted');
});

test('fake with override', function () {
    $response = DeletedBatchResponse::fake([
        'id' => 'msgbatch_custom123',
    ]);

    expect($response)
        ->id->toBe('msgbatch_custom123');
});
