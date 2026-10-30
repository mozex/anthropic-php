<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Factory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Lists the models through a client the factory builds, and returns the request that went out.
 */
function requestSentBy(Factory $factory): RequestInterface
{
    $history = [];

    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode(modelList())),
    ]));
    $stack->push(Middleware::history($history));

    $factory->withApiKey('foo')->withHttpClient(new GuzzleClient(['handler' => $stack]))->make()->models()->list();

    return $history[0]['request'];
}

it('may create a client', function () {
    $anthropic = Anthropic::client('foo');

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('may create a client via factory', function () {
    $anthropic = Anthropic::factory()
        ->withApiKey('foo')
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sets a custom client via factory', function () {
    $anthropic = Anthropic::factory()
        ->withHttpClient(new GuzzleClient)
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sends requests to the default base url', function () {
    $default = requestSentBy(Anthropic::factory());
    $blank = requestSentBy(Anthropic::factory()->withBaseUri(''));

    expect((string) $default->getUri())->toBe('https://api.anthropic.com/v1/models')
        ->and((string) $blank->getUri())->toBe('https://api.anthropic.com/v1/models');
});

it('sets a custom base url via factory', function () {
    $request = requestSentBy(Anthropic::factory()->withBaseUri('https://anthropic.example.com/v1'));

    expect((string) $request->getUri())->toBe('https://anthropic.example.com/v1/models');
});

it('sends requests under a base url as given, without a doubled slash', function () {
    $atRoot = requestSentBy(Anthropic::factory()->withBaseUri('https://proxy.example.com'));
    $withSlash = requestSentBy(Anthropic::factory()->withBaseUri('https://anthropic.example.com/v1/'));

    expect((string) $atRoot->getUri())->toBe('https://proxy.example.com/models')
        ->and((string) $withSlash->getUri())->toBe('https://anthropic.example.com/v1/models');
});

it('sends the default API version', function () {
    $request = requestSentBy(Anthropic::factory());

    expect($request->getHeaderLine('anthropic-version'))->toBe('2023-06-01');
});

it('sets the API version via factory', function () {
    $request = requestSentBy(Anthropic::factory()->withApiVersion('2026-10-21'));

    expect($request->getHeaderLine('anthropic-version'))->toBe('2026-10-21');
});

it('sends the default API version when the given one is empty', function () {
    $request = requestSentBy(Anthropic::factory()->withApiVersion(' '));

    expect($request->getHeaderLine('anthropic-version'))->toBe('2023-06-01');
});

it('lets a custom anthropic-version header override the API version', function () {
    $headerOnly = requestSentBy(Anthropic::factory()->withHttpHeader('anthropic-version', '2026-10-21'));

    $both = requestSentBy(Anthropic::factory()
        ->withApiVersion('2026-10-21')
        ->withHttpHeader('anthropic-version', '2023-06-01'));

    expect($headerOnly->getHeaderLine('anthropic-version'))->toBe('2026-10-21')
        ->and($both->getHeaderLine('anthropic-version'))->toBe('2023-06-01');
});

it('sets a custom header via factory', function () {
    $anthropic = Anthropic::factory()
        ->withHttpHeader('X-My-Header', 'foo')
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sets a custom query parameter via factory', function () {
    $anthropic = Anthropic::factory()
        ->withQueryParam('my-param', 'bar')
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sets a custom stream handler via factory', function () {
    $anthropic = Anthropic::factory()
        ->withHttpClient($client = new GuzzleClient)
        ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $client->send($request, ['stream' => true]))
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});
