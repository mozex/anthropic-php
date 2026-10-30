<?php

declare(strict_types=1);

use Anthropic\Resources\Messages;
use Anthropic\Resources\Models;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\ClientFake;
use PHPUnit\Framework\ExpectationFailedException;

test('a connection view of the fake shares its queue and records, under its name', function () {
    $fake = new ClientFake([CreateResponse::fake(), CreateResponse::fake()]);

    $fake->onConnection('tenant')->messages()->create(['model' => 'claude-opus-5-5']);
    $fake->messages()->create(['model' => 'claude-sonnet-5-5']);

    $fake->assertSent(Messages::class, 2);
    $fake->assertSentOn('tenant', Messages::class, fn (string $method, array $parameters): bool => $parameters['model'] === 'claude-opus-5-5');
    $fake->assertSentOn('tenant', Messages::class, 1);
    $fake->assertNotSentOn('tenant', Messages::class, fn (string $method, array $parameters): bool => $parameters['model'] === 'claude-sonnet-5-5');
    $fake->assertNotSentOn('other', Messages::class);

    expect(fn () => $fake->assertSentOn('tenant', Messages::class, fn (string $method, array $parameters): bool => $parameters['model'] === 'claude-sonnet-5-5'))
        ->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertSentOn('other', Messages::class))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertSentOn('tenant', Messages::class, 2))->toThrow(ExpectationFailedException::class, 'sent 1 times on the [tenant] connection instead of 2 times')
        ->and(fn () => $fake->assertNotSentOn('tenant', Messages::class))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertNotSentOn('tenant', Messages::class, fn (string $method, array $parameters): bool => $parameters['model'] === 'claude-opus-5-5'))->toThrow(ExpectationFailedException::class);
});

test('a connection view keeps its name through withOptions()', function () {
    $fake = new ClientFake([CreateResponse::fake()]);

    $fake->onConnection('tenant')->withOptions(['workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy'])->messages()->create(['model' => 'claude-opus-5-5']);

    $fake->assertSentOn('tenant', Messages::class, 1);
    $fake->assertNotSentOn('other', Messages::class);
    $fake->assertSentWithOptions(Messages::class, ['workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy']);
});

test('a subclass can name the connection of the fake\'s own calls', function () {
    $fake = new class([CreateResponse::fake(), RetrieveResponse::fake()]) extends ClientFake
    {
        protected ?string $connection = 'default';
    };

    $fake->messages()->create(['model' => 'claude-opus-5-5']);
    $fake->onConnection('support')->models()->retrieve('claude-opus-5-5');

    $fake->assertSentOn('default', Messages::class, 1);
    $fake->assertSentOn('support', Models::class, 1);
    $fake->assertNotSentOn('default', Models::class);
});
