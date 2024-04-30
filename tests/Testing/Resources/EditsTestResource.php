<?php

use Anthropic\Resources\Edits;
use Anthropic\Responses\Edits\CreateResponse;
use Anthropic\Testing\ClientFake;

it('records a edits create request', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->edits()->create([
        'model' => 'text-davinci-edit-001',
        'input' => 'What day of the wek is it?',
        'instruction' => 'Fix the spelling mistakes',
    ]);

    $fake->assertSent(Edits::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'text-davinci-edit-001' &&
            $parameters['input'] === 'What day of the wek is it?' &&
            $parameters['instruction'] === 'Fix the spelling mistakes';
    });
});
