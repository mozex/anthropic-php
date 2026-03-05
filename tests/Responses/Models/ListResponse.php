<?php

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;

test('from', function () {
    $result = ListResponse::from(modelList(), meta());

    expect($result)
        ->toBeInstanceOf(ListResponse::class)
        ->data->toBeArray()->toHaveCount(2)
        ->data->each->toBeInstanceOf(RetrieveResponse::class)
        ->firstId->toBe('claude-sonnet-4-6')
        ->lastId->toBe('claude-haiku-4-5')
        ->hasMore->toBeTrue()
        ->meta()->toBeInstanceOf(MetaInformation::class);

    expect($result->data[0])
        ->id->toBe('claude-sonnet-4-6')
        ->displayName->toBe('Claude Sonnet 4.6');

    expect($result->data[1])
        ->id->toBe('claude-haiku-4-5')
        ->displayName->toBe('Claude Haiku 4.5');
});

test('as array accessible', function () {
    $result = ListResponse::from(modelList(), meta());

    expect(isset($result['data']))->toBeTrue();

    expect($result['data'])->toBeArray()->toHaveCount(2);
});

test('to array', function () {
    $result = ListResponse::from(modelList(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(modelList());
});

test('fake', function () {
    $response = ListResponse::fake();

    expect($response)
        ->data->toBeArray()->toHaveCount(1)
        ->firstId->toBe('claude-sonnet-4-6')
        ->hasMore->toBeFalse();
});

test('fake with override', function () {
    $response = ListResponse::fake([
        'has_more' => true,
    ]);

    expect($response)
        ->hasMore->toBeTrue();
});
