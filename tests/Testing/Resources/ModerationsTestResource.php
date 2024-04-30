<?php

use Anthropic\Resources\Moderations;
use Anthropic\Responses\Moderations\CreateResponse;
use Anthropic\Testing\ClientFake;

it('records a moderations create request', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->moderations()->create([
        'model' => 'text-moderation-latest',
        'input' => 'I want to k*** them.',
    ]);

    $fake->assertSent(Moderations::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'text-moderation-latest' &&
            $parameters['input'] === 'I want to k*** them.';
    });
});
