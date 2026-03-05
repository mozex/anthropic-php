<?php

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;

test('list', function () {
    $client = mockClient('GET', 'models', [], \Anthropic\ValueObjects\Transporter\Response::from(modelList(), metaHeaders()), validateParams: false);

    $result = $client->models()->list();

    expect($result)
        ->toBeInstanceOf(ListResponse::class)
        ->data->toBeArray()->toHaveCount(2)
        ->data->each->toBeInstanceOf(RetrieveResponse::class)
        ->firstId->toBe('claude-sonnet-4-6')
        ->lastId->toBe('claude-haiku-4-5')
        ->hasMore->toBeTrue();

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('retrieve', function () {
    $client = mockClient('GET', 'models/claude-sonnet-4-6', [], \Anthropic\ValueObjects\Transporter\Response::from(modelRetrieve(), metaHeaders()));

    $result = $client->models()->retrieve('claude-sonnet-4-6');

    expect($result)
        ->toBeInstanceOf(RetrieveResponse::class)
        ->id->toBe('claude-sonnet-4-6')
        ->type->toBe('model')
        ->createdAt->toBe('2025-05-14T00:00:00Z')
        ->displayName->toBe('Claude Sonnet 4.6');

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});
