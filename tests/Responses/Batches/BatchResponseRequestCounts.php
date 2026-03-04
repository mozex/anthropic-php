<?php

use Anthropic\Responses\Batches\BatchResponseRequestCounts;

test('from', function () {
    $result = BatchResponseRequestCounts::from(batchResponse()['request_counts']);

    expect($result)
        ->toBeInstanceOf(BatchResponseRequestCounts::class)
        ->processing->toBe(0)
        ->succeeded->toBe(95)
        ->errored->toBe(3)
        ->canceled->toBe(1)
        ->expired->toBe(1);
});

test('to array', function () {
    $result = BatchResponseRequestCounts::from(batchResponse()['request_counts']);

    expect($result->toArray())
        ->toBeArray()
        ->toBe(batchResponse()['request_counts']);
});
