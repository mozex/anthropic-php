<?php

use Anthropic\Responses\Files\FileResponse;
use Anthropic\Responses\Files\FileResponseScope;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $result = FileResponse::from(fileResponse(), meta());

    expect($result)
        ->toBeInstanceOf(FileResponse::class)
        ->id->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->type->toBe('file')
        ->filename->toBe('document.pdf')
        ->mimeType->toBe('application/pdf')
        ->sizeBytes->toBe(1024000)
        ->createdAt->toBe('2025-01-01T00:00:00Z')
        ->downloadable->toBeFalse()
        ->scope->toBeNull()
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('from with scope', function () {
    $result = FileResponse::from(fileScopedResponse(), meta());

    expect($result->scope)
        ->toBeInstanceOf(FileResponseScope::class)
        ->id->toBe('session_01AbCdEfGhIjKlMnOpQrStUv')
        ->type->toBe('session');
});

test('from without downloadable', function () {
    $attributes = fileResponse();
    unset($attributes['downloadable']);

    $result = FileResponse::from($attributes, meta());

    expect($result->downloadable)->toBeNull();
});

test('as array accessible', function () {
    $result = FileResponse::from(fileResponse(), meta());

    expect(isset($result['id']))->toBeTrue();
    expect($result['id'])->toBe('file_011CNha8iCJcU1wXNR6q4V8w');
});

test('to array', function () {
    $result = FileResponse::from(fileResponse(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(fileResponse());
});

test('to array with scope', function () {
    $result = FileResponse::from(fileScopedResponse(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(fileScopedResponse());
});

test('to array omits missing optional fields', function () {
    $attributes = fileResponse();
    unset($attributes['downloadable']);

    $result = FileResponse::from($attributes, meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe($attributes)
        ->not->toHaveKey('downloadable')
        ->not->toHaveKey('scope');
});

test('fake', function () {
    $response = FileResponse::fake();

    expect($response)
        ->id->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->type->toBe('file')
        ->filename->toBe('document.pdf')
        ->mimeType->toBe('application/pdf');
});

test('fake with override', function () {
    $response = FileResponse::fake([
        'filename' => 'dataset.csv',
        'mime_type' => 'text/csv',
        'size_bytes' => 42,
    ]);

    expect($response)
        ->filename->toBe('dataset.csv')
        ->mimeType->toBe('text/csv')
        ->sizeBytes->toBe(42);
});
