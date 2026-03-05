<?php

use Anthropic\Resources\Messages;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Testing\ClientFake;

it('records a messages create request', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello!'],
        ],
    ]);

    $fake->assertSent(Messages::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'claude-sonnet-4-6' &&
            $parameters['messages'][0]['role'] === 'user' &&
            $parameters['messages'][0]['content'] === 'Hello!';
    });
});

it('records a streamed create create request', function () {
    $fake = new ClientFake([
        CreateStreamedResponse::fake(),
    ]);

    $fake->messages()->createStreamed([
        'model' => 'claude-sonnet-4-6',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello!'],
        ],
    ]);

    $fake->assertSent(Messages::class, function ($method, $parameters) {
        return $method === 'createStreamed' &&
            $parameters['model'] === 'claude-sonnet-4-6' &&
            $parameters['messages'][0]['role'] === 'user' &&
            $parameters['messages'][0]['content'] === 'Hello!';
    });
});
