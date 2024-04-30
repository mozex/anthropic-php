<?php

use GuzzleHttp\Client as GuzzleClient;
use OpenAI\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('may create a client', function () {
    $openAI = Anthropic::client('foo');

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets organization when provided', function () {
    $openAI = Anthropic::client('foo', 'nunomaduro');

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('may create a client via factory', function () {
    $openAI = Anthropic::factory()
        ->withApiKey('foo')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets an organization via factory', function () {
    $openAI = Anthropic::factory()
        ->withOrganization('nunomaduro')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom client via factory', function () {
    $openAI = Anthropic::factory()
        ->withHttpClient(new GuzzleClient())
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom base url via factory', function () {
    $openAI = Anthropic::factory()
        ->withBaseUri('https://openai.example.com/v1')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom header via factory', function () {
    $openAI = Anthropic::factory()
        ->withHttpHeader('X-My-Header', 'foo')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom query parameter via factory', function () {
    $openAI = Anthropic::factory()
        ->withQueryParam('my-param', 'bar')
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});

it('sets a custom stream handler via factory', function () {
    $openAI = Anthropic::factory()
        ->withHttpClient($client = new GuzzleClient())
        ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $client->send($request, ['stream' => true]))
        ->make();

    expect($openAI)->toBeInstanceOf(Client::class);
});
