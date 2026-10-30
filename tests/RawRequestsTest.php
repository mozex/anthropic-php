<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Exceptions\NotFoundException;
use Anthropic\Resources\Raw;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\Testing\ClientFake;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A client over a mocked Guzzle client that records every request it sends.
 *
 * @param  array<int, array{request: RequestInterface}>  $history
 */
function rawClient(array &$history, Response ...$queue): Client
{
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($history));

    return Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient(new GuzzleClient(['handler' => $stack]))
        ->make();
}

function rawJson(array $data, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json', 'request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'], json_encode($data));
}

test('a GET sends its parameters in the query and returns the decoded body', function () {
    $history = [];
    $client = rawClient($history, rawJson(skillsListDocsExample()));

    $response = $client->request('GET', 'skills', ['limit' => 20, 'source' => 'custom', 'betas' => ['skills-2025-10-02']]);

    expect($response)->toBeInstanceOf(RawResponse::class)
        ->and($response->status)->toBe(200)
        ->and($response->data)->toBe(skillsListDocsExample())
        ->and($response->meta()->requestId)->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        ->and((string) $history[0]['request']->getUri())->toBe('https://api.anthropic.com/v1/skills?limit=20&source=custom')
        ->and($history[0]['request']->getHeaderLine('anthropic-beta'))->toBe('skills-2025-10-02')
        ->and($history[0]['request']->getHeaderLine('x-api-key'))->toBe('foo');
});

test('a POST sends its parameters as a JSON body', function () {
    $history = [];
    $client = rawClient($history, rawJson(messagesCountTokens()));
    $parameters = ['model' => 'claude-opus-5-5', 'messages' => [['role' => 'user', 'content' => 'Hello, world']]];

    $response = $client->request('POST', '/v1/messages/count_tokens', $parameters);

    expect($response->data)->toBe(messagesCountTokens())
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/v1/messages/count_tokens')
        ->and((string) $history[0]['request']->getBody())->toBe(json_encode($parameters));
});

test('a body that is not a JSON object or array comes back as text', function (Response $response, string $expected) {
    $history = [];

    expect(rawClient($history, $response)->request('DELETE', 'skills/skill_01JAbcdefghijklmnopqrstuvw')->data)->toBe($expected);
})->with([
    // composed: no docs example shows these bodies
    'an empty 204' => [new Response(204), ''],
    'plain text' => [new Response(200, ['Content-Type' => 'text/plain'], 'ok'), 'ok'],
    'a JSON string' => [new Response(200, ['Content-Type' => 'application/json'], '"ok"'), '"ok"'],
    'broken JSON' => [new Response(200, ['Content-Type' => 'application/json'], '{"id": '), '{"id": '],
    'JSON under another type' => [new Response(200, ['Content-Type' => 'text/plain'], '{"id": 1}'), '{"id": 1}'],
    'one JSONL line' => [new Response(200, ['Content-Type' => 'application/x-jsonl'], '{"id": 1}'."\n"), '{"id": 1}'."\n"],
]);

test('a JSON body is decoded whatever the media type is spelled like', function (string $contentType) {
    $history = [];
    $response = new Response(200, ['Content-Type' => $contentType], json_encode(skillsListDocsExample()));

    expect(rawClient($history, $response)->request('GET', 'skills')->data)->toBe(skillsListDocsExample());
})->with(['application/json', 'Application/JSON; charset=utf-8', 'application/problem+json']);

test('the status comes back as the API sent it', function () {
    $history = [];

    // composed: a create that answers 201
    expect(rawClient($history, rawJson(['id' => 'skill_01JAbcdefghijklmnopqrstuvw'], 201))->request('POST', 'skills')->status)->toBe(201);
});

test('an error status throws its typed exception, as on every other method', function () {
    $history = [];
    $client = rawClient($history, rawJson([
        'type' => 'error',
        'error' => ['type' => 'not_found_error', 'message' => 'The requested resource could not be found.'],
        'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
    ], 404));

    expect(fn () => $client->request('GET', 'skills/skill_01JAbcdefghijklmnopqrstuvw'))->toThrow(NotFoundException::class, 'The requested resource could not be found.');
});

test('an unknown method throws before anything is sent', function () {
    $history = [];

    expect(fn () => rawClient($history)->request('PATCH', 'skills'))->toThrow(InvalidArgumentException::class)
        ->and($history)->toBe([]);
});

test('a raw request carries the options of the client it is sent from', function () {
    $history = [];
    $client = rawClient($history, rawJson(skillsListDocsExample()));

    $client->withOptions(['workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy'])->request('GET', 'skills');

    expect($history[0]['request']->getHeaderLine('anthropic-workspace-id'))->toBe('wrkspc_011CZkZaBF1tNoB5wlCeusgy');
});

test('the fake returns a queued raw response and records the request under Raw::class', function () {
    $fake = new ClientFake([RawResponse::fake(skillsListDocsExample())]);

    $response = $fake->request('GET', 'skills', ['source' => 'custom']);

    expect($response->data)->toBe(skillsListDocsExample())
        ->and($response->status)->toBe(200);

    $fake->assertSent(Raw::class, fn (string $method, string $httpMethod, string $path, array $parameters): bool => $method === 'request'
        && $httpMethod === 'GET'
        && $path === 'skills'
        && $parameters === ['source' => 'custom']);
});

test('the fake throws on a method the client would reject, before anything is recorded', function () {
    $fake = new ClientFake([RawResponse::fake()]);

    expect(fn () => $fake->request('PATCH', 'skills'))->toThrow(InvalidArgumentException::class);

    $fake->assertNothingSent();
});

test('a fake raw response takes a text body, a status and meta information', function () {
    $response = RawResponse::fake('', status: 204);

    expect($response->data)->toBe('')
        ->and($response->status)->toBe(204)
        ->and($response->meta()->requestId)->toBe('req_011CSHoEeqs5C35K2UUqR7Fy');
});

test('the fake records the options of a raw request', function () {
    $fake = new ClientFake([RawResponse::fake()]);

    $fake->withOptions(['betas' => ['skills-2025-10-02']])->request('GET', 'skills');

    $fake->assertSentWithOptions(Raw::class, ['betas' => ['skills-2025-10-02']]);
});
