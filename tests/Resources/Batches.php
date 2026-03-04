<?php

use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;
use Anthropic\Responses\Meta\MetaInformation;

test('create', function () {
    $client = mockClient('POST', 'messages/batches', [
        'requests' => [
            [
                'custom_id' => 'request-1',
                'params' => [
                    'model' => 'claude-sonnet-4-6',
                    'max_tokens' => 1024,
                    'messages' => [['role' => 'user', 'content' => 'Hello!']],
                ],
            ],
        ],
    ], \Anthropic\ValueObjects\Transporter\Response::from(batchResponse(), metaHeaders()));

    $result = $client->batches()->create([
        'requests' => [
            [
                'custom_id' => 'request-1',
                'params' => [
                    'model' => 'claude-sonnet-4-6',
                    'max_tokens' => 1024,
                    'messages' => [['role' => 'user', 'content' => 'Hello!']],
                ],
            ],
        ],
    ]);

    expect($result)
        ->toBeInstanceOf(BatchResponse::class)
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->type->toBe('message_batch')
        ->processingStatus->toBe('ended');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('retrieve', function () {
    $client = mockClient('GET', 'messages/batches/msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', [], \Anthropic\ValueObjects\Transporter\Response::from(batchResponse(), metaHeaders()));

    $result = $client->batches()->retrieve('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

    expect($result)
        ->toBeInstanceOf(BatchResponse::class)
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->processingStatus->toBe('ended');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('list', function () {
    $client = mockClient('GET', 'messages/batches', [], \Anthropic\ValueObjects\Transporter\Response::from(batchListResponse(), metaHeaders()), validateParams: false);

    $result = $client->batches()->list();

    expect($result)
        ->toBeInstanceOf(BatchListResponse::class)
        ->data->toBeArray()->toHaveCount(2)
        ->data->each->toBeInstanceOf(BatchResponse::class)
        ->firstId->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->lastId->toBe('msgbatch_07V2nm5PqB3bP8szLgTmn1EG')
        ->hasMore->toBeFalse();

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('cancel', function () {
    $cancelingResponse = batchInProgressResponse();
    $cancelingResponse['processing_status'] = 'canceling';

    $client = mockClient('POST', 'messages/batches/msgbatch_07V2nm5PqB3bP8szLgTmn1EG/cancel', [], \Anthropic\ValueObjects\Transporter\Response::from($cancelingResponse, metaHeaders()), validateParams: false);

    $result = $client->batches()->cancel('msgbatch_07V2nm5PqB3bP8szLgTmn1EG');

    expect($result)
        ->toBeInstanceOf(BatchResponse::class)
        ->processingStatus->toBe('canceling');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('delete', function () {
    $client = mockClient('DELETE', 'messages/batches/msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', [], \Anthropic\ValueObjects\Transporter\Response::from(deletedBatchResponse(), metaHeaders()));

    $result = $client->batches()->delete('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

    expect($result)
        ->toBeInstanceOf(DeletedBatchResponse::class)
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->type->toBe('message_batch_deleted');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});
