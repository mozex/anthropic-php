<?php

use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\BatchResponseRequestCounts;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $result = BatchResponse::from(batchResponse(), meta());

    expect($result)
        ->toBeInstanceOf(BatchResponse::class)
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->type->toBe('message_batch')
        ->processingStatus->toBe('ended')
        ->requestCounts->toBeInstanceOf(BatchResponseRequestCounts::class)
        ->createdAt->toBe('2025-04-01T12:00:00Z')
        ->expiresAt->toBe('2025-04-02T12:00:00Z')
        ->endedAt->toBe('2025-04-01T12:30:00Z')
        ->cancelInitiatedAt->toBeNull()
        ->archivedAt->toBeNull()
        ->resultsUrl->toBe('https://api.anthropic.com/v1/messages/batches/msgbatch_04Rka1yCsMLGPnR7kfPdgR8x/results')
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('from in progress', function () {
    $result = BatchResponse::from(batchInProgressResponse(), meta());

    expect($result)
        ->processingStatus->toBe('in_progress')
        ->endedAt->toBeNull()
        ->resultsUrl->toBeNull()
        ->requestCounts->processing->toBe(50)
        ->requestCounts->succeeded->toBe(0);
});

test('as array accessible', function () {
    $result = BatchResponse::from(batchResponse(), meta());

    expect(isset($result['id']))->toBeTrue();

    expect($result['id'])->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');
});

test('to array', function () {
    $result = BatchResponse::from(batchResponse(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(batchResponse());
});

test('fake', function () {
    $response = BatchResponse::fake();

    expect($response)
        ->id->toBe('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->type->toBe('message_batch')
        ->processingStatus->toBe('ended');
});

test('fake with override', function () {
    $response = BatchResponse::fake([
        'processing_status' => 'in_progress',
    ]);

    expect($response)
        ->processingStatus->toBe('in_progress');
});
