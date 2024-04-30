<?php

use Anthropic\Client;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('may create a client', function () {
    $anthropic = Anthropic::client('foo');

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sets organization when provided', function () {
    $anthropic = Anthropic::client('foo', 'nunomaduro');

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('may create a client via factory', function () {
    $anthropic = Anthropic::factory()
        ->withApiKey('foo')
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sets an organization via factory', function () {
    $anthropic = Anthropic::factory()
        ->withOrganization('nunomaduro')
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sets a custom client via factory', function () {
    $anthropic = Anthropic::factory()
        ->withHttpClient(new GuzzleClient())
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});

it('sets a custom base url via factory', function () {
    $anthropic = Anthropic::factory()
        ->withBaseUri('https://openai.example.com/v1')
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
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
        ->withHttpClient($client = new GuzzleClient())
        ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $client->send($request, ['stream' => true]))
        ->make();

    expect($anthropic)->toBeInstanceOf(Client::class);
});
