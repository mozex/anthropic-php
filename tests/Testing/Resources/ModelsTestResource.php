<?php

use Anthropic\Resources\Models;
use Anthropic\Responses\Models\DeleteResponse;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\ClientFake;

it('records a model retrieve request', function () {
    $fake = new ClientFake([
        RetrieveResponse::fake(),
    ]);

    $fake->models()->retrieve('gpt-3.5-turbo-instruct');

    $fake->assertSent(Models::class, function ($method, $parameters) {
        return $method === 'retrieve' &&
            $parameters === 'gpt-3.5-turbo-instruct';
    });
});

it('records a model delete request', function () {
    $fake = new ClientFake([
        DeleteResponse::fake(),
    ]);

    $fake->models()->delete('curie:ft-acmeco-2021-03-03-21-44-20');

    $fake->assertSent(Models::class, function ($method, $parameters) {
        return $method === 'delete' &&
            $parameters === 'curie:ft-acmeco-2021-03-03-21-44-20';
    });
});

it('records a model list request', function () {
    $fake = new ClientFake([
        ListResponse::fake(),
    ]);

    $fake->models()->list();

    $fake->assertSent(Models::class, function ($method) {
        return $method === 'list';
    });
});
