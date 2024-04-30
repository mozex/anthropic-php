<?php

use Anthropic\Resources\Embeddings;
use Anthropic\Responses\Embeddings\CreateResponse;
use Anthropic\Testing\ClientFake;

it('records a embeddings create request', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->embeddings()->create([
        'model' => 'text-similarity-babbage-001',
        'input' => 'The food was delicious and the waiter...',
    ]);

    $fake->assertSent(Embeddings::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'text-similarity-babbage-001' &&
            $parameters['input'] === 'The food was delicious and the waiter...';
    });
});
