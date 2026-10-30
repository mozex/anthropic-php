<?php

declare(strict_types=1);

use Anthropic\Responses\Batches\BatchIndividualResponse;
use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;

test('the batch docs examples round-trip through toArray()', function (string $class, string $name) {
    $response = $class::from(json_decode(batchesDocsExamples()[$name], true, flags: JSON_THROW_ON_ERROR), meta());

    expect(docsExampleJson($response->toArray()))->toBe(docsExampleJson(batchesDocsExamples()[$name]));
})->with([
    'batch' => [BatchResponse::class, 'batch'],
    'list' => [BatchListResponse::class, 'list'],
    'deleted' => [DeletedBatchResponse::class, 'deleted'],
]);

test('succeeded, canceled and expired result lines round-trip through toArray()', function (string $name) {
    $line = batchResultsDocsExamples()[$name];
    $result = BatchIndividualResponse::from(json_decode($line, true, flags: JSON_THROW_ON_ERROR));

    $expected = json_decode($line, flags: JSON_THROW_ON_ERROR);

    if (isset($expected->result->message)) {
        docsExampleWithAlwaysEmittedKeys($expected->result->message);
    }

    expect(docsExampleJson($result->toArray()))->toBe(docsExampleJson($expected));
})->with(['succeeded', 'succeeded (second)', 'canceled', 'expired']);

test('an errored result keeps the request id of its error', function () {
    $result = BatchIndividualResponse::from(json_decode(batchResultsDocsExamples()['errored'], true, flags: JSON_THROW_ON_ERROR));

    expect($result->result->error)
        ->type->toBe('not_found_error')
        ->message->toBe('The requested resource could not be found.')
        ->requestId->toBe('req_011CSHoEeqs5C35K2UUqR7Fy')
        ->and($result->toArray())->toBe([
            'custom_id' => 'my-third-request',
            'result' => [
                'type' => 'errored',
                'error' => [
                    'type' => 'not_found_error',
                    'message' => 'The requested resource could not be found.',
                    'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
                ],
            ],
        ]);
});

test('an errored result keeps the details of its error', function () {
    $result = BatchIndividualResponse::from(json_decode(batchResultsDocsExamples()['errored (spend cap)'], true, flags: JSON_THROW_ON_ERROR));

    expect($result->result->error->requestId)->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        ->and($result->toArray()['result']['error']['details'])->toBe(['error_code' => 'enforced_spend_limit_reached']);
});

test('a result line builds again from its own toArray()', function (string $line) {
    $result = BatchIndividualResponse::from(json_decode($line, true, flags: JSON_THROW_ON_ERROR));

    $rebuilt = BatchIndividualResponse::from($result->toArray());

    expect($rebuilt->toArray())->toBe($result->toArray())
        ->and($rebuilt->result->error?->requestId)->toBe($result->result->error?->requestId);
})->with(fn (): array => batchResultsDocsExamples());

test('a batch page without ids builds', function (array $attributes, array $expected) {
    $page = BatchListResponse::from($attributes, meta());

    expect($page)
        ->firstId->toBeNull()
        ->lastId->toBeNull()
        ->and($page->toArray())->toBe($expected);
})->with([
    // composed: no docs example of an empty page
    'sent as null' => [
        ['data' => [], 'first_id' => null, 'last_id' => null, 'has_more' => false],
        ['data' => [], 'first_id' => null, 'last_id' => null, 'has_more' => false],
    ],
    'left out' => [
        ['data' => [], 'has_more' => false],
        ['data' => [], 'has_more' => false, 'first_id' => null, 'last_id' => null],
    ],
]);

test('an errored result whose error has no request id gains none', function () {
    // composed: the reference types request_id as "or null"; this wrapper leaves it out
    $result = BatchIndividualResponse::from(['custom_id' => 'my-third-request', 'result' => ['type' => 'errored', 'error' => [
        'type' => 'error',
        'error' => ['type' => 'not_found_error', 'message' => 'The requested resource could not be found.'],
    ]]]);

    expect($result->result->error->requestId)->toBeNull()
        ->and($result->toArray()['result']['error'])->toBe(['type' => 'not_found_error', 'message' => 'The requested resource could not be found.']);
});

test('an errored result keeps unknown keys of the error wrapper and its place among the result keys', function () {
    // composed: no docs example of a wrapper or a result with keys beyond the documented ones
    $attributes = ['custom_id' => 'my-third-request', 'result' => [
        'type' => 'errored',
        'error' => [
            'type' => 'error',
            'error' => ['type' => 'not_found_error', 'message' => 'The requested resource could not be found.'],
            'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
            'future_field' => ['kind' => 'note'],
        ],
        'future_result_field' => true,
    ]];

    $result = BatchIndividualResponse::from($attributes)->toArray()['result'];

    expect(array_keys($result))->toBe(['type', 'error', 'future_result_field'])
        ->and($result['error'])->toBe([
            'type' => 'not_found_error',
            'message' => 'The requested resource could not be found.',
            'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
            'future_field' => ['kind' => 'note'],
        ]);
});
