<?php

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Models\RetrieveResponse;

test('from', function () {
    $result = RetrieveResponse::from(modelRetrieve(), meta());

    expect($result)
        ->toBeInstanceOf(RetrieveResponse::class)
        ->id->toBe('claude-sonnet-4-6-20250514')
        ->type->toBe('model')
        ->createdAt->toBe('2025-05-14T00:00:00Z')
        ->displayName->toBe('Claude Sonnet 4.6 (2025-05-14)')
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('as array accessible', function () {
    $result = RetrieveResponse::from(modelRetrieve(), meta());

    expect(isset($result['id']))->toBeTrue();

    expect($result['id'])->toBe('claude-sonnet-4-6-20250514');
});

test('to array', function () {
    $result = RetrieveResponse::from(modelRetrieve(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(modelRetrieve());
});

test('fake', function () {
    $response = RetrieveResponse::fake();

    expect($response)
        ->id->toBe('claude-sonnet-4-6-20250514')
        ->type->toBe('model');
});

test('fake with override', function () {
    $response = RetrieveResponse::fake([
        'id' => 'claude-opus-4-5-20251101',
        'display_name' => 'Claude Opus 4.5',
    ]);

    expect($response)
        ->id->toBe('claude-opus-4-5-20251101')
        ->displayName->toBe('Claude Opus 4.5');
});
