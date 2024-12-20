<?php

use Anthropic\Resources\Completions;
use Anthropic\Responses\Completions\CreateResponse;
use Anthropic\Testing\ClientFake;
use PHPUnit\Framework\ExpectationFailedException;

it('returns a fake response', function () {
    $fake = new ClientFake([
        CreateResponse::fake([
            'completion' => 'awesome!',
        ]),
    ]);

    $completion = $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    expect($completion['completion'])->toBe('awesome!');
});

it('throws fake exceptions', function () {
    $fake = new ClientFake([
        new \Anthropic\Exceptions\ErrorException([
            'message' => 'Overloaded',
            'type' => 'overloaded_error',
        ], 404),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);
})->expectExceptionMessage('Overloaded');

it('throws an exception if there is no more fake response', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);
})->expectExceptionMessage('No fake responses left');

it('allows to add more responses', function () {
    $fake = new ClientFake([
        CreateResponse::fake([
            'id' => 'cmpl-1',
        ]),
    ]);

    $completion = $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    expect($completion)
        ->id->toBe('cmpl-1');

    $fake->addResponses([
        CreateResponse::fake([
            'id' => 'cmpl-2',
        ]),
    ]);

    $completion = $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    expect($completion)
        ->id->toBe('cmpl-2');
});

it('asserts a request was sent', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->assertSent(Completions::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'claude-2.1' &&
            $parameters['prompt'] === 'PHP is ';
    });
});

it('throws an exception if a request was not sent', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->assertSent(Completions::class, function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'claude-2.1' &&
            $parameters['prompt'] === 'PHP is ';
    });
})->expectException(ExpectationFailedException::class);

it('asserts a request was sent on the resource', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->completions()->assertSent(function ($method, $parameters) {
        return $method === 'create' &&
            $parameters['model'] === 'claude-2.1' &&
            $parameters['prompt'] === 'PHP is ';
    });
});

it('asserts a request was sent n times', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->assertSent(Completions::class, 2);
});

it('throws an exception if a request was not sent n times', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->assertSent(Completions::class, 2);
})->expectException(ExpectationFailedException::class);

it('asserts a request was not sent', function () {
    $fake = new ClientFake;

    $fake->assertNotSent(Completions::class);
});

it('throws an exception if an unexpected request was sent', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->assertNotSent(Completions::class);
})->expectException(ExpectationFailedException::class);

it('asserts a request was not sent on the resource', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->completions()->assertNotSent();
});

it('asserts no request was sent', function () {
    $fake = new ClientFake;

    $fake->assertNothingSent();
});

it('throws an exception if any request was sent when non was expected', function () {
    $fake = new ClientFake([
        CreateResponse::fake(),
    ]);

    $fake->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'PHP is ',
    ]);

    $fake->assertNothingSent();
})->expectException(ExpectationFailedException::class);
