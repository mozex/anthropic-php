<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\BadRequestException;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Middleware\FallbackState;
use Anthropic\Middleware\RefusalFallbackMiddleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that answers from a queue and records every request it gets.
 */
class FallbackRecordingClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? throw new RuntimeException('No responses left.');
    }

    /**
     * @return array<string, mixed>
     */
    public function body(int $index): array
    {
        return json_decode((string) $this->requests[$index]->getBody(), true);
    }
}

/**
 * A client that sends through the middleware to the recording client.
 */
function fallbackClient(FallbackRecordingClient $http, RefusalFallbackMiddleware $middleware, int $maxRetries = 0): Client
{
    return Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient($http)
        ->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $http->sendRequest($request))
        ->withMiddleware($middleware)
        ->withMaxRetries($maxRetries)
        ->withRetrySleep(fn (int $milliseconds) => null)
        ->make();
}

function fallbackJson(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
}

/**
 * The refusal from the fallback credit beta's reference, with its token.
 *
 * @return array<string, mixed>
 */
function fallbackRefusal(): array
{
    return json_decode(messagesDocsExamples()['fallback credit'], true);
}

/**
 * Claude Opus 4.8's answer from the server-side fallback example. Composed: without the `fallback` block and the
 * `usage.iterations` the server stitches in, as the fallback model answers a request of its own.
 *
 * @return array<string, mixed>
 */
function fallbackServed(): array
{
    $served = json_decode(messagesDocsExamples()['server-side fallback with trigger'], true);
    $served['content'] = array_values(array_filter($served['content'], fn (array $block): bool => $block['type'] !== 'fallback'));
    unset($served['usage']['iterations']);

    return $served;
}

/**
 * The request from the fallback credit guide's example.
 *
 * @return array<string, mixed>
 */
function fallbackRequest(array $extra = []): array
{
    return ['model' => 'claude-fable-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello, Claude']], ...$extra];
}

test('a refusal is sent again on the next model with its credit token, and the answer marks the switch', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson(fallbackServed()));

    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect($http->requests)->toHaveCount(2)
        ->and($http->body(0))->toBe(fallbackRequest())
        ->and($http->body(1))->toBe(fallbackRequest([
            'model' => 'claude-opus-4-8',
            'fallback_credit_token' => ['token' => 'QW50aHJvcGljL0NsYXVkZQ==', 'mode' => 'best_effort'],
        ]))
        ->and($http->requests[0]->getHeaderLine('anthropic-beta'))->toBe('fallback-credit-2026-07-01')
        ->and($http->requests[1]->getHeaderLine('anthropic-beta'))->toBe('fallback-credit-2026-07-01')
        ->and($http->requests[0]->getHeaderLine('Accept-Encoding'))->toBe('identity')
        ->and($response->model)->toBe('claude-opus-4-8')
        ->and($response->toArray()['content'])->toBe([
            ['type' => 'fallback', 'from' => ['model' => 'claude-fable-5'], 'to' => ['model' => 'claude-opus-4-8'], 'trigger' => ['type' => 'refusal', 'category' => 'cyber']],
            ['type' => 'text', 'text' => 'Hi! How can I help you today?'],
        ])
        // the stitched envelope matches what a server-side fallback answers; the typed iteration counts a missing
        // cache field as 0
        ->and($response->toArray()['usage']['iterations'])->toBe([
            ['type' => 'message', 'model' => 'claude-fable-5', 'input_tokens' => 412, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
            ['type' => 'fallback_message', 'model' => 'claude-opus-4-8', 'input_tokens' => 412, 'output_tokens' => 264, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
        ]);
});

test('an answer that is not a refusal comes back as it was', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackServed()));

    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect($http->requests)->toHaveCount(1)
        ->and($response->toArray())->toMatchArray(fallbackServed());
});

test('the chain is walked in order until a model accepts', function () {
    // composed: Claude Opus 5 refuses too, with the reference's refusal
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson([...fallbackRefusal(), 'model' => 'claude-opus-5']), fallbackJson(fallbackServed()));

    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-5', 'claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect(array_map(fn (int $index): string => $http->body($index)['model'], [0, 1, 2]))->toBe(['claude-fable-5', 'claude-opus-5', 'claude-opus-4-8'])
        ->and(array_column(array_column($response->toArray()['content'], 'to'), 'model'))->toBe(['claude-opus-5', 'claude-opus-4-8'])
        ->and(array_column($response->toArray()['usage']['iterations'], 'type'))->toBe(['message', 'message', 'fallback_message']);
});

test('when every model refuses, the last refusal comes back', function () {
    // composed: Claude Opus 4.8 refuses too
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson([...fallbackRefusal(), 'model' => 'claude-opus-4-8']));

    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    // as a server-side chain answers: a message entry for each earlier attempt, the last as fallback_message
    expect($response->stop_reason)->toBe('refusal')
        ->and($response->model)->toBe('claude-opus-4-8')
        ->and($response->toArray()['content'][0]['type'])->toBe('fallback')
        ->and(array_column($response->toArray()['usage']['iterations'], 'type'))->toBe(['message', 'fallback_message']);
});

test('a shared state pins later requests to the model that accepted', function () {
    $state = new FallbackState;
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson(fallbackServed()), fallbackJson(fallbackServed()));
    $client = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8'], state: $state));

    $client->messages()->create(fallbackRequest());
    $next = $client->messages()->create(fallbackRequest());

    expect($state->index)->toBe(0)
        ->and($http->requests)->toHaveCount(3)
        ->and($http->body(2)['model'])->toBe('claude-opus-4-8')
        ->and($next->toArray())->toMatchArray(fallbackServed());
});

test('a refusal without a credit token is sent again without one', function () {
    $refusal = json_decode(messagesDocsExamples()['refusal'], true);
    $http = new FallbackRecordingClient(fallbackJson($refusal), fallbackJson(fallbackServed()));

    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect($http->body(1))->toBe(fallbackRequest(['model' => 'claude-opus-4-8']));
});

test('a token the API rejects is dropped, unless redemption is only unavailable for now', function () {
    // composed: the error bodies, with the messages the fallback credit guide names
    $rejected = fallbackJson(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'fallback_credit_token: the token is invalid']], 400);
    $unavailable = fn (): Response => fallbackJson(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'fallback_credit_token: redemption temporarily unavailable']], 400);

    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), $rejected, fallbackJson(fallbackServed()));
    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect($http->requests)->toHaveCount(3)
        ->and($http->body(2))->toBe(fallbackRequest(['model' => 'claude-opus-4-8']))
        ->and($response->model)->toBe('claude-opus-4-8');

    // only unavailable for now: one more try with the same token
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), $unavailable(), fallbackJson(fallbackServed()));
    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect($http->requests)->toHaveCount(3)
        ->and($http->body(2)['fallback_credit_token'])->toBe(['token' => 'QW50aHJvcGljL0NsYXVkZQ==', 'mode' => 'best_effort']);

    // still unavailable: the credit is forfeited
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), $unavailable(), $unavailable(), fallbackJson(fallbackServed()));
    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect($http->requests)->toHaveCount(4)
        ->and($http->body(3))->not->toHaveKey('fallback_credit_token');
});

test('fast mode is dropped when the fallback is rate limited or overloaded, unless the entry asks for it', function (array $entry, int $requests, ?string $lastSpeed) {
    // composed: a rate-limit error body
    $limited = fallbackJson(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'Number of request tokens has exceeded your per-minute rate limit']], 429);
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), $limited, fallbackJson(fallbackServed()));

    try {
        fallbackClient($http, new RefusalFallbackMiddleware([$entry]))->messages()->create(fallbackRequest(['speed' => 'fast']));
    } catch (RateLimitException) {
        //
    }

    expect($http->requests)->toHaveCount($requests)
        ->and($http->body($requests - 1)['speed'] ?? null)->toBe($lastSpeed);
})->with([
    'an entry without speed' => [['model' => 'claude-opus-4-8'], 3, null],
    'an entry that keeps fast mode' => [['model' => 'claude-opus-4-8', 'speed' => 'fast'], 2, 'fast'],
]);

test('fast mode dropped on the way stays dropped when an unavailable redemption is tried again', function () {
    // composed: the error bodies
    $limited = fallbackJson(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'Number of request tokens has exceeded your per-minute rate limit']], 429);
    $unavailable = fallbackJson(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'fallback_credit_token: redemption temporarily unavailable']], 400);
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), $limited, $unavailable, fallbackJson(fallbackServed()));

    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest(['speed' => 'fast']));

    expect($http->requests)->toHaveCount(4)
        ->and($http->body(3))->not->toHaveKey('speed')
        ->and($http->body(3))->toHaveKey('fallback_credit_token');
});

test('an entry overrides max_tokens, output_config one field at a time, and thinking', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson(fallbackServed()));
    $middleware = new RefusalFallbackMiddleware([['model' => 'claude-opus-4-8', 'max_tokens' => 2048, 'output_config' => ['effort' => null], 'unknown' => true]]);

    // composed: a format, an effort and between_tools thinking on one request
    fallbackClient($http, $middleware)->messages()->create(fallbackRequest([
        'output_config' => ['effort' => 'high', 'format' => ['type' => 'json_schema', 'schema' => ['type' => 'object']]],
        'thinking' => ['type' => 'between_tools'],
    ]));

    expect($http->body(1))->toMatchArray([
        'model' => 'claude-opus-4-8',
        'max_tokens' => 2048,
        'output_config' => ['format' => ['type' => 'json_schema', 'schema' => ['type' => 'object']]],
        // only Claude Sonnet 5.5 takes between_tools
        'thinking' => ['type' => 'disabled'],
    ])->and($http->body(1))->not->toHaveKey('unknown');
});

test('an empty object in the history goes out as an empty object on every attempt', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson(fallbackServed()));
    // composed: a parameterless tool call in the history
    $messages = [
        ['role' => 'user', 'content' => 'What time is it?'],
        ['role' => 'assistant', 'content' => [['type' => 'tool_use', 'id' => 'toolu_01A09q90qw90lq917835lq9', 'name' => 'get_time', 'input' => new ArrayObject]]],
        ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_01A09q90qw90lq917835lq9', 'content' => '12:00']]],
    ];

    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest(['messages' => $messages]));

    expect((string) $http->requests[0]->getBody())->toContain('"input":{}')
        ->and((string) $http->requests[1]->getBody())->toContain('"input":{}');
});

test('the fallback blocks at the front of a turn are taken out of the history, one further in stays', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackServed()));
    $seam = ['type' => 'fallback', 'from' => ['model' => 'claude-fable-5'], 'to' => ['model' => 'claude-opus-4-8'], 'trigger' => ['type' => 'refusal', 'category' => 'cyber']];

    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest(['messages' => [
        ['role' => 'user', 'content' => 'Hello, Claude'],
        ['role' => 'assistant', 'content' => [$seam, ['type' => 'text', 'text' => 'Hi! How can I help you today?']]],
        // composed: a turn that holds only a fallback block
        ['role' => 'assistant', 'content' => [$seam]],
        ['role' => 'user', 'content' => 'Thanks'],
        // composed: a server-side fallback in the middle of an answer
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi!'], $seam, ['type' => 'text', 'text' => 'How can I help you today?']]],
        ['role' => 'user', 'content' => 'Bye'],
    ]]));

    expect($http->body(0)['messages'])->toBe([
        ['role' => 'user', 'content' => 'Hello, Claude'],
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi! How can I help you today?']]],
        ['role' => 'user', 'content' => 'Thanks'],
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi!'], $seam, ['type' => 'text', 'text' => 'How can I help you today?']]],
        ['role' => 'user', 'content' => 'Bye'],
    ]);
});

test('the callback hears each fallback, and every model that already refused is skipped', function () {
    $calls = [];
    // composed: Claude Opus 5 refuses too
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson([...fallbackRefusal(), 'model' => 'claude-opus-5']), fallbackJson(fallbackServed()));
    $middleware = new RefusalFallbackMiddleware(['claude-opus-5', 'claude-fable-5', 'claude-opus-4-8'], function (array $message, array $entry, int $index) use (&$calls): void {
        $calls[] = [$message['model'], $entry['model'], $index];
    });

    fallbackClient($http, $middleware)->messages()->create(fallbackRequest());

    expect($calls)->toBe([['claude-fable-5', 'claude-opus-5', 0], ['claude-opus-5', 'claude-opus-4-8', 2]])
        ->and($http->requests)->toHaveCount(3);
});

test('a fallback block names the model that answered, as the API resolved it', function () {
    // composed: an answer whose model carries a date, as an alias resolves
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson([...fallbackServed(), 'model' => 'claude-haiku-4-5-20251001']));

    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-haiku-4-5']))->messages()->create(fallbackRequest());

    expect($response->toArray()['content'][0]['to'])->toBe(['model' => 'claude-haiku-4-5-20251001']);
});

test('iterations the answer brought are kept behind the refused attempts', function () {
    $served = json_decode(messagesDocsExamples()['threshold compaction'], true);
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), fallbackJson($served));

    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest());

    expect(array_column($response->toArray()['usage']['iterations'], 'type'))->toBe(['message', ...array_column($served['usage']['iterations'], 'type')]);
});

test('a retry the transporter makes after an overloaded fallback starts over with a fresh token', function () {
    $state = new FallbackState;
    // composed: an overloaded error body
    $overloaded = fallbackJson(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529);
    $http = new FallbackRecordingClient(fallbackJson(fallbackRefusal()), $overloaded, fallbackJson(fallbackRefusal()), fallbackJson(fallbackServed()));

    $response = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8'], state: $state), maxRetries: 1)->messages()->create(fallbackRequest());

    expect(array_map(fn (int $index): string => $http->body($index)['model'], [0, 1, 2, 3]))->toBe(['claude-fable-5', 'claude-opus-4-8', 'claude-fable-5', 'claude-opus-4-8'])
        ->and($http->body(3))->toHaveKey('fallback_credit_token')
        ->and($response->toArray()['content'][0]['type'])->toBe('fallback')
        ->and($state->index)->toBe(0);
});

test('a fallback that fails leaves the pin where it was', function () {
    $state = new FallbackState;
    // composed: the fallback rejects the request
    $rejected = fallbackJson(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'max_tokens: 1000000 > 128000, which is the maximum allowed']], 400);
    $http = new FallbackRecordingClient(fallbackJson(json_decode(messagesDocsExamples()['refusal'], true)), $rejected);

    expect(fn () => fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8'], state: $state))->messages()->create(fallbackRequest()))
        ->toThrow(BadRequestException::class)
        ->and($state->index)->toBeNull();
});

test('the beta joins the ones the request already sends', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackServed()));

    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest(['betas' => ['compact-2026-09-04']]));

    expect($http->requests[0]->getHeaderLine('anthropic-beta'))->toBe('compact-2026-09-04,fallback-credit-2026-07-01');
});

test('streams, server-side fallbacks and other paths pass through untouched', function (Closure $call, array $sent) {
    // composed: an empty stream
    $http = new FallbackRecordingClient(new Response(200, ['Content-Type' => 'text/event-stream'], ''), fallbackJson(fallbackServed()));
    $client = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']));

    $call($client);

    expect($http->requests)->toHaveCount(1)
        ->and($http->requests[0]->hasHeader('anthropic-beta'))->toBeFalse()
        ->and($http->requests[0]->hasHeader('Accept-Encoding'))->toBeFalse()
        ->and((string) $http->requests[0]->getBody())->toBe($sent === [] ? '' : json_encode($sent));
})->with([
    'a stream' => [fn (Client $client) => iterator_to_array($client->messages()->createStreamed(fallbackRequest())), fallbackRequest(['stream' => true])],
    'a server-side fallback' => [fn (Client $client) => $client->request('POST', 'messages', fallbackRequest(['fallback' => ['model' => 'claude-opus-4-8']])), fallbackRequest(['fallback' => ['model' => 'claude-opus-4-8']])],
    'a models list' => [fn (Client $client) => $client->request('GET', 'models'), []],
]);

test('token counting and batches are not touched', function () {
    $http = new FallbackRecordingClient(fallbackJson(messagesCountTokens()), fallbackJson(batchResponse()));
    $client = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']));

    $client->messages()->countTokens(fallbackRequest());
    $client->batches()->create(['requests' => [['custom_id' => 'my-first-request', 'params' => fallbackRequest()]]]);

    expect($http->requests)->toHaveCount(2)
        ->and($http->requests[0]->getHeaderLine('anthropic-beta'))->not->toContain('fallback-credit')
        ->and($http->requests[1]->getHeaderLine('anthropic-beta'))->not->toContain('fallback-credit');
});

test('a request with the server-side fallbacks list throws before anything is sent', function () {
    $http = new FallbackRecordingClient;

    expect(fn () => fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest(['fallbacks' => 'default'])))
        ->toThrow(InvalidArgumentException::class, 'fallbacks')
        ->and($http->requests)->toBe([]);
});

test('a chain entry needs a model, and a state must fit the chain', function () {
    $state = new FallbackState;
    $state->index = 3;
    $http = new FallbackRecordingClient(fallbackJson(fallbackServed()));

    expect(fn () => new RefusalFallbackMiddleware([['max_tokens' => 2048]]))->toThrow(InvalidArgumentException::class, 'needs a model')
        ->and(fn () => fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8'], state: $state))->messages()->create(fallbackRequest()))
        ->toThrow(InvalidArgumentException::class, 'entry 3');
});
