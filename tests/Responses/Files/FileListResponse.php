<?php

use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $result = FileListResponse::from(fileListResponse(), meta());

    expect($result)
        ->toBeInstanceOf(FileListResponse::class)
        ->data->toBeArray()->toHaveCount(2)
        ->data->each->toBeInstanceOf(FileResponse::class)
        ->firstId->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->lastId->toBe('file_011CPMxVD3fHLUhvTqtsQA5w')
        ->hasMore->toBeFalse()
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('from empty list', function () {
    $result = FileListResponse::from([
        'data' => [],
        'first_id' => null,
        'last_id' => null,
        'has_more' => false,
    ], meta());

    expect($result)
        ->data->toBe([])
        ->firstId->toBeNull()
        ->lastId->toBeNull()
        ->hasMore->toBeFalse();
});

test('from without pagination metadata', function () {
    $result = FileListResponse::from([
        'data' => [fileResponse()],
    ], meta());

    expect($result)
        ->firstId->toBeNull()
        ->lastId->toBeNull()
        ->hasMore->toBeFalse();
});

test('as array accessible', function () {
    $result = FileListResponse::from(fileListResponse(), meta());

    expect(isset($result['data']))->toBeTrue();
    expect($result['first_id'])->toBe('file_011CNha8iCJcU1wXNR6q4V8w');
});

test('to array', function () {
    $result = FileListResponse::from(fileListResponse(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(fileListResponse());
});

test('fake', function () {
    $response = FileListResponse::fake();

    expect($response)
        ->toBeInstanceOf(FileListResponse::class)
        ->data->toHaveCount(1)
        ->data->each->toBeInstanceOf(FileResponse::class)
        ->hasMore->toBeFalse();
});
