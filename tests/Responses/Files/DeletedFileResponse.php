<?php

use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $result = DeletedFileResponse::from(deletedFileResponse(), meta());

    expect($result)
        ->toBeInstanceOf(DeletedFileResponse::class)
        ->id->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->type->toBe('file_deleted')
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('from defaults type when missing', function () {
    $result = DeletedFileResponse::from([
        'id' => 'file_011CNha8iCJcU1wXNR6q4V8w',
    ], meta());

    expect($result->type)->toBe('file_deleted');
});

test('as array accessible', function () {
    $result = DeletedFileResponse::from(deletedFileResponse(), meta());

    expect(isset($result['id']))->toBeTrue();
    expect($result['id'])->toBe('file_011CNha8iCJcU1wXNR6q4V8w');
});

test('to array', function () {
    $result = DeletedFileResponse::from(deletedFileResponse(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(deletedFileResponse());
});

test('fake', function () {
    $response = DeletedFileResponse::fake();

    expect($response)
        ->id->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->type->toBe('file_deleted');
});
