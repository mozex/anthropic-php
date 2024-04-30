<?php

use Anthropic\Resources\Completions;
use Anthropic\Responses\Completions\CreateResponse;
use Anthropic\Responses\Completions\CreateStreamedResponse;
use Anthropic\Testing\ClientFake;

it('records a completions create request', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'gpt-3.5-turbo-instruct',
        'prompt' => 'PHP is ',
    ]);

    $fake->assertSent(Completions::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'gpt-3.5-turbo-instruct' &&
            $parameters['prompt'] === 'PHP is ';
    });
});

it('records a streamed completions create request', function () {
    $fake = new ClientFake([
        CreateStreamedResponse::fake(),
    ]);

    $fake->completions()->createStreamed([
        'model' => 'gpt-3.5-turbo-instruct',
        'prompt' => 'PHP is ',
    ]);

    $fake->assertSent(Completions::class, function ($method, $parameters) {
        return $method === 'createStreamed' &&
            $parameters['model'] === 'gpt-3.5-turbo-instruct' &&
            $parameters['prompt'] === 'PHP is ';
    });
});
