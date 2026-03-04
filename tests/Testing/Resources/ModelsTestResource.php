<?php

use Anthropic\Resources\Models;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\ClientFake;

it('records a models list request', function () {
    $fake = new ClientFake([
        ListResponse::fake(),
    ]);

    $fake->models()->list(['limit' => 10]);

    $fake->assertSent(Models::class, function ($method, $parameters) {
        return $method === 'list' &&
            $parameters === ['limit' => 10];
    });
});

it('records a models retrieve request', function () {
    $fake = new ClientFake([
        RetrieveResponse::fake(),
    ]);

    $fake->models()->retrieve('claude-sonnet-4-6');

    $fake->assertSent(Models::class, function ($method, $model) {
        return $method === 'retrieve' &&
            $model === 'claude-sonnet-4-6';
    });
});
