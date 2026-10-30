<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\BadRequestException;
use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\RetryableException;
use Anthropic\Middleware\FallbackState;
use Anthropic\Middleware\RefusalFallbackMiddleware;
use GuzzleHttp\Psr7\Request;
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

test('every fallback block is taken out of the history, one in the middle of a turn too', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackServed()));
    $seam = ['type' => 'fallback', 'from' => ['model' => 'claude-fable-5'], 'to' => ['model' => 'claude-opus-4-8'], 'trigger' => ['type' => 'refusal', 'category' => 'cyber']];

    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest(['messages' => [
        ['role' => 'user', 'content' => 'Hello, Claude'],
        ['role' => 'assistant', 'content' => [$seam, ['type' => 'text', 'text' => 'Hi! How can I help you today?']]],
        // composed: a turn that holds only a fallback block
        ['role' => 'assistant', 'content' => [$seam]],
        ['role' => 'user', 'content' => 'Thanks'],
        // composed: a fallback in the middle of an answer, where a streamed fallback puts it
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi!'], $seam, ['type' => 'text', 'text' => 'How can I help you today?']]],
        ['role' => 'user', 'content' => 'Bye'],
    ]]));

    expect($http->body(0)['messages'])->toBe([
        ['role' => 'user', 'content' => 'Hello, Claude'],
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi! How can I help you today?']]],
        ['role' => 'user', 'content' => 'Thanks'],
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi!'], ['type' => 'text', 'text' => 'How can I help you today?']]],
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

test('server-side fallbacks and other paths pass through untouched', function (Closure $call, array $sent) {
    $http = new FallbackRecordingClient(fallbackJson(fallbackServed()), fallbackJson(fallbackServed()));
    $client = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']));

    $call($client);

    expect($http->requests)->toHaveCount(1)
        ->and($http->requests[0]->hasHeader('anthropic-beta'))->toBeFalse()
        ->and($http->requests[0]->hasHeader('Accept-Encoding'))->toBeFalse()
        ->and((string) $http->requests[0]->getBody())->toBe($sent === [] ? '' : json_encode($sent));
})->with([
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

/**
 * A stream from the official SDK's refusal fallback fixtures, its text changed by the replacements.
 *
 * @param  array<string, string>  $replace
 */
function fallbackSse(string $name, array $replace = []): Response
{
    return new Response(200, ['Content-Type' => 'text/event-stream'], strtr(file_get_contents(__DIR__.'/Helpers/Streams/'.$name.'.txt'), $replace));
}

/**
 * The spliced stream's events, decoded, read through the middleware alone.
 *
 * @param  list<ResponseInterface|Throwable>  $responses
 * @param  list<RequestInterface>  $sent
 * @return list<array<string, mixed>>
 */
function splicedEvents(RefusalFallbackMiddleware $middleware, array $responses, array &$sent = []): array
{
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages', ['Content-Type' => 'application/json'], json_encode(fallbackRequest(['stream' => true])));

    $response = $middleware($request, function (RequestInterface $request) use (&$responses, &$sent): ResponseInterface {
        $sent[] = $request;
        $next = array_shift($responses) ?? throw new RuntimeException('No responses left.');

        return $next instanceof Throwable ? throw $next : $next;
    });

    // getContents(), not a string cast, which can't throw
    preg_match_all('/^data: (.+)$/m', $response->getBody()->getContents(), $matches);

    return array_map(fn (string $data): array => json_decode($data, true), $matches[1]);
}

/**
 * @param  list<array<string, mixed>>  $events
 * @return list<array{0: int, 1: string}>
 */
function splicedBlocks(array $events): array
{
    return array_map(fn (array $event): array => [$event['index'], $event['content_block']['type']], splicedOfType($events, 'content_block_start'));
}

/**
 * @param  list<array<string, mixed>>  $events
 * @return list<array<string, mixed>>
 */
function splicedOfType(array $events, string $type): array
{
    return array_values(array_filter($events, fn (array $event): bool => $event['type'] === $type));
}

/**
 * @param  list<RequestInterface>  $sent
 * @return list<array<string, mixed>>
 */
function splicedBodies(array $sent): array
{
    return array_map(fn (RequestInterface $request): array => json_decode((string) $request->getBody(), true), $sent);
}

test('a refused stream goes on with the fallback behind a fallback block, as one message', function () {
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), fallbackSse('RefusalFallbackServed')]);

    $delta = splicedOfType($events, 'message_delta');

    expect(splicedBlocks($events))->toBe([[0, 'thinking'], [1, 'text'], [2, 'fallback'], [3, 'text']])
        ->and(splicedOfType($events, 'content_block_start')[2]['content_block'])
        ->toBe(['type' => 'fallback', 'from' => ['model' => 'claude-fable-5'], 'to' => ['model' => 'claude-opus-4-8'], 'trigger' => ['type' => 'refusal', 'category' => null]])
        ->and(splicedOfType($events, 'message_start'))->toHaveCount(1)
        ->and(splicedOfType($events, 'message_start')[0]['message']['id'])->toBe('msg_fixture_a_0001')
        ->and(splicedOfType($events, 'message_stop'))->toHaveCount(1)
        ->and($delta)->toHaveCount(1)
        ->and($delta[0]['delta']['stop_reason'])->toBe('end_turn')
        ->and(array_map(fn (array $iteration): array => [$iteration['type'], $iteration['model']], $delta[0]['usage']['iterations']))
        ->toBe([['message', 'claude-fable-5'], ['fallback_message', 'claude-opus-4-8']])
        ->and(array_column(splicedOfType($events, 'content_block_stop'), 'index'))->toBe([0, 1, 2, 3]);
});

test('the fallback request carries the token, and the refused partial as an assistant turn to carry on from', function () {
    $sent = [];
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), fallbackSse('RefusalFallbackServed')], $sent);

    $retry = splicedBodies($sent)[1];

    expect($sent)->toHaveCount(2)
        ->and($retry['model'])->toBe('claude-opus-4-8')
        ->and($retry['stream'])->toBeTrue()
        ->and($retry['max_tokens'])->toBe(1024)
        ->and($retry['fallback_credit_token'])->toBe(['token' => 'tok_synthetic_fixture_a', 'mode' => 'best_effort'])
        ->and($retry['messages'])->toBe([
            ['role' => 'user', 'content' => 'Hello, Claude'],
            ['role' => 'assistant', 'content' => [
                ['type' => 'thinking', 'thinking' => 'Simple educational question about what a solar eclipse is. This is benign general knowledge — definitions are fine. Also the user called me "claudius" — I\'m Claude. Minor correction or just roll with it politely.', 'signature' => 'c3ludGhldGljLXNpZ25hdHVyZS1maXh0dXJlLWEtbm90LWEtcmVhbC1zaWduYXR1cmU='],
                ['type' => 'text', 'text' => 'Hi'],
            ]],
        ])
        ->and($sent[0]->getHeaderLine('anthropic-beta'))->toBe('fallback-credit-2026-07-01')
        ->and($sent[1]->getHeaderLine('anthropic-beta'))->toBe('fallback-credit-2026-07-01');
});

test('a spliced stream folds into one message, billed for both models', function () {
    $http = new FallbackRecordingClient(fallbackSse('RefusalFallbackRefused'), fallbackSse('RefusalFallbackServed'));

    $message = fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->createStreamed(fallbackRequest())->finalMessage();

    expect(array_column($message->toArray()['content'], 'type'))->toBe(['thinking', 'text', 'fallback', 'text'])
        ->and($message->model)->toBe('claude-opus-4-8')
        ->and($message->id)->toBe('msg_fixture_a_0001')
        ->and($message->content[1]->text)->toBe('Hi')
        ->and($message->content[3]->text)->toStartWith(' there! Happy to explain.')
        // 28 in and 106 out refused, then 31 in and 547 out served
        ->and($message->usage->billedTokens())->toBe(712);
});

test('a refused tool call is claimed with its input decoded', function () {
    $sent = [];
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackToolRefused'), fallbackSse('RefusalFallbackServed')], $sent);

    $claim = splicedBodies($sent)[1]['messages'][1]['content'];

    expect(splicedBlocks($events))->toBe([[0, 'server_tool_use'], [1, 'web_search_tool_result'], [2, 'text'], [3, 'fallback'], [4, 'text']])
        ->and(array_column($claim, 'type'))->toBe(['server_tool_use', 'web_search_tool_result', 'text'])
        ->and($claim[0])->toBe(['type' => 'server_tool_use', 'id' => 'srvtoolu_fixture_a_0001', 'name' => 'web_search', 'input' => ['query' => 'solar eclipse viewing safety news 2026']])
        ->and($claim[1]['tool_use_id'])->toBe('srvtoolu_fixture_a_0001');
});

test('without a prefill claim the fallback answers the request from the start', function () {
    $sent = [];
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused', ['"fallback_has_prefill_claim":true' => '"fallback_has_prefill_claim":false']), fallbackSse('RefusalFallbackServed')], $sent);

    expect(splicedBodies($sent)[1]['messages'])->toBe([['role' => 'user', 'content' => 'Hello, Claude']]);
});

test('a 400 on the claim is tried again without it, keeping the token, then without the token', function () {
    // composed: the error body, with the message the fallback credit guide names
    $rejected = fn (): Response => fallbackJson(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'fallback_credit_token: the token is invalid']], 400);
    $sent = [];
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), $rejected(), $rejected(), fallbackSse('RefusalFallbackServed')], $sent);

    $bodies = splicedBodies($sent);

    expect($sent)->toHaveCount(4)
        ->and($bodies[1]['messages'])->toHaveCount(2)
        ->and($bodies[2]['messages'])->toHaveCount(1)
        ->and($bodies[2]['fallback_credit_token']['token'])->toBe('tok_synthetic_fixture_a')
        ->and($bodies[3])->not->toHaveKey('fallback_credit_token');
});

test('a fallback that fails or never answers gives way to the next, with the same token', function () {
    // composed: the overloaded error from the errors page
    $overloaded = fallbackJson(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529);
    $sent = [];
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-5', 'claude-sonnet-5-5', 'claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), $overloaded, ConnectionException::fake(), fallbackSse('RefusalFallbackServed')], $sent);

    $bodies = splicedBodies($sent);
    $seam = splicedOfType($events, 'content_block_start')[2]['content_block'];

    expect(array_column($bodies, 'model'))->toBe(['claude-fable-5', 'claude-opus-5', 'claude-sonnet-5-5', 'claude-opus-4-8'])
        ->and(array_column(array_column(array_slice($bodies, 1), 'fallback_credit_token'), 'token'))->toBe(['tok_synthetic_fixture_a', 'tok_synthetic_fixture_a', 'tok_synthetic_fixture_a'])
        ->and([$seam['from']['model'], $seam['to']['model']])->toBe(['claude-fable-5', 'claude-opus-4-8'])
        ->and(splicedBlocks($events))->toHaveCount(4);
});

test('when no fallback answers, the refusal goes out as it came, with the attempts and the rate limited model', function () {
    // composed: the rate limit error from the errors page
    $limited = fallbackJson(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'Your account has hit a rate limit.']], 429);
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), $limited]);

    $delta = splicedOfType($events, 'message_delta')[0];

    expect(splicedBlocks($events))->toBe([[0, 'thinking'], [1, 'text']])
        ->and($delta['delta']['stop_reason'])->toBe('refusal')
        ->and($delta['delta']['stop_details']['recommended_model'])->toBe('claude-opus-4-8')
        ->and(array_column($delta['usage']['iterations'], 'model'))->toBe(['claude-fable-5'])
        ->and(splicedOfType($events, 'message_stop'))->toHaveCount(1);
});

test('a refusal after output without a token is not tried again', function () {
    $sent = [];
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused', ['"fallback_credit_token":"tok_synthetic_fixture_a",' => ''])], $sent);

    expect($sent)->toHaveCount(1)
        ->and(splicedOfType($events, 'message_delta')[0]['delta']['stop_reason'])->toBe('refusal');
});

test('a refusal before any output starts over for free, under the first message ID', function () {
    $sent = [];
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackRefusedEarly(), fallbackSse('RefusalFallbackServed')], $sent);

    expect(splicedBlocks($events))->toBe([[0, 'fallback'], [1, 'text']])
        ->and(splicedOfType($events, 'message_start'))->toHaveCount(1)
        ->and(splicedOfType($events, 'message_start')[0]['message']['id'])->toBe('msg_fixture_a_0001')
        ->and(splicedOfType($events, 'message_start')[0]['message']['model'])->toBe('claude-opus-4-8')
        ->and(splicedBodies($sent)[1])->not->toHaveKey('fallback_credit_token');
});

test('a stream that is not refused passes on as it came, but for its pings', function () {
    preg_match_all('/^data: (.+)$/m', (string) fallbackSse('RefusalFallbackServed')->getBody(), $matches);
    $expected = array_values(array_filter(array_map(fn (string $data): array => json_decode($data, true), $matches[1]), fn (array $event): bool => $event['type'] !== 'ping'));

    expect(splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackServed')]))->toBe($expected);
});

test('a shared state pins later streams to the model that served', function () {
    $state = new FallbackState;
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8'], state: $state), [fallbackSse('RefusalFallbackRefused'), fallbackSse('RefusalFallbackServed')]);

    $sent = [];
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8'], state: $state), [fallbackSse('RefusalFallbackServed')], $sent);

    expect($state->index)->toBe(0)
        ->and(splicedBodies($sent)[0]['model'])->toBe('claude-opus-4-8');
});

test('the callback hears the refused message and the entry a stream falls back to', function () {
    $heard = [];
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8'], function (array $message, array $entry, int $index) use (&$heard) {
        $heard[] = [$message['id'], $message['stop_reason'], $entry['model'], $index];
    }), [fallbackSse('RefusalFallbackRefused'), fallbackSse('RefusalFallbackServed')]);

    expect($heard)->toBe([['msg_fixture_a_0001', 'refusal', 'claude-opus-4-8', 0]]);
});

test('an error other than a connection failure on a fallback reaches the reader', function () {
    expect(fn () => splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), new RuntimeException('The token budget is used up.')]))
        ->toThrow(RuntimeException::class, 'The token budget is used up.');
});

test('the spliced stream is read as it comes, not all at once', function () {
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages', [], json_encode(fallbackRequest(['stream' => true])));
    $body = (new RefusalFallbackMiddleware(['claude-opus-4-8']))($request, fn (): ResponseInterface => fallbackSse('RefusalFallbackServed'))->getBody();

    $first = $body->read(65536);

    expect($first)->toContain('message_start')
        ->and($first)->not->toContain('message_stop')
        ->and($body->eof())->toBeFalse();
});

/**
 * The refused fixture as another model's answer, or with the start carrying more.
 *
 * @param  array<string, string>  $replace
 */
function fallbackRefusedBy(string $model, array $replace = []): Response
{
    return fallbackSse('RefusalFallbackRefused', ['"model":"claude-fable-5"' => '"model":"'.$model.'"', ...$replace]);
}

/**
 * The refused fixture cut to its start, refusal and stop: a refusal before any output, without a token.
 */
function fallbackRefusedEarly(string $model = 'claude-fable-5'): Response
{
    // composed: the refused fixture's start and refusal, with nothing streamed between them and no token
    preg_match_all('/^event: (?:message_start|message_delta|message_stop)\ndata: .+$/m', (string) fallbackRefusedBy($model)->getBody(), $matches);

    return new Response(200, ['Content-Type' => 'text/event-stream'], str_replace('"fallback_credit_token":"tok_synthetic_fixture_a",', '', implode("\n\n", $matches[0])."\n\n"));
}

/**
 * The docs' input transformations, for a start to carry.
 */
function fallbackTransformations(): string
{
    return '"input_transformations":[{"type":"thinking_dropped","path":"messages.3.content.0","reason":"model_binding_mismatch"}]';
}

test('a chain of refusals after output marks each switch, and claims every partial in turn', function () {
    $sent = [];
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-5', 'claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), fallbackRefusedBy('claude-opus-5'), fallbackSse('RefusalFallbackServed')], $sent);

    $seams = array_column(array_filter(splicedOfType($events, 'content_block_start'), fn (array $event): bool => $event['content_block']['type'] === 'fallback'), 'content_block');

    expect(splicedBlocks($events))->toBe([[0, 'thinking'], [1, 'text'], [2, 'fallback'], [3, 'thinking'], [4, 'text'], [5, 'fallback'], [6, 'text']])
        ->and(array_map(fn (array $seam): array => [$seam['from']['model'], $seam['to']['model']], array_values($seams)))->toBe([['claude-fable-5', 'claude-opus-5'], ['claude-opus-5', 'claude-opus-4-8']])
        ->and(array_column(splicedBodies($sent)[2]['messages'][1]['content'], 'type'))->toBe(['thinking', 'text', 'thinking', 'text'])
        ->and(array_map(fn (array $iteration): array => [$iteration['type'], $iteration['model']], splicedOfType($events, 'message_delta')[0]['usage']['iterations']))
        ->toBe([['message', 'claude-fable-5'], ['message', 'claude-opus-5'], ['fallback_message', 'claude-opus-4-8']]);
});

test('a chain of refusals before any output marks every switch in order', function () {
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-5', 'claude-opus-4-8']), [fallbackRefusedEarly(), fallbackRefusedEarly('claude-opus-5'), fallbackSse('RefusalFallbackServed')]);

    $seams = array_column(splicedOfType($events, 'content_block_start'), 'content_block');

    expect(splicedBlocks($events))->toBe([[0, 'fallback'], [1, 'fallback'], [2, 'text']])
        ->and([$seams[0]['from']['model'], $seams[0]['to']['model'], $seams[1]['from']['model'], $seams[1]['to']['model']])
        ->toBe(['claude-fable-5', 'claude-opus-5', 'claude-opus-5', 'claude-opus-4-8']);
});

test('a fallback that ends or fails before any output leaves no switch behind, and the next takes over', function (Response $dead) {
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-5', 'claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), $dead, fallbackSse('RefusalFallbackServed')]);

    $seam = splicedOfType($events, 'content_block_start')[2]['content_block'];

    expect(splicedBlocks($events))->toBe([[0, 'thinking'], [1, 'text'], [2, 'fallback'], [3, 'text']])
        ->and([$seam['from']['model'], $seam['to']['model']])->toBe(['claude-fable-5', 'claude-opus-4-8']);
})->with([
    // composed: a stream with nothing in it
    'one that ends' => fn () => new Response(200, ['Content-Type' => 'text/event-stream'], ''),
    // the streaming page's error event
    'one that fails' => fn () => new Response(200, ['Content-Type' => 'text/event-stream'], "event: error\ndata: {\"type\": \"error\", \"error\": {\"type\": \"overloaded_error\", \"message\": \"Overloaded\"}}\n\n"),
]);

test('a streamed fallback block names the model that answered, as the API resolved it', function () {
    // composed: an answer whose model carries a date, as an alias resolves
    $served = fallbackSse('RefusalFallbackServed', ['"model":"claude-opus-4-8"' => '"model":"claude-haiku-4-5-20251001"']);
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-haiku-4-5']), [fallbackSse('RefusalFallbackRefused'), $served]);

    expect(splicedOfType($events, 'content_block_start')[2]['content_block']['to'])->toBe(['model' => 'claude-haiku-4-5-20251001'])
        // the answer that served is named as resolved too; the refused attempt as the request named it
        ->and(array_column(splicedOfType($events, 'message_delta')[0]['usage']['iterations'], 'model'))->toBe(['claude-fable-5', 'claude-haiku-4-5-20251001']);
});

test('a fallback start that is not sent passes its input transformations on, and one that is sent keeps them', function () {
    $served = fallbackSse('RefusalFallbackServed', ['"content":[]' => '"content":[],'.fallbackTransformations()]);
    $spliced = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), $served]);

    $served = fallbackSse('RefusalFallbackServed', ['"content":[]' => '"content":[],'.fallbackTransformations()]);
    $early = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackRefusedEarly(), $served]);

    expect(splicedOfType($spliced, 'message_delta')[0]['input_transformations'][0]['type'])->toBe('thinking_dropped')
        // the start went out with them, so the delta doesn't repeat them
        ->and(splicedOfType($early, 'message_start')[0]['message']['input_transformations'][0]['type'])->toBe('thinking_dropped')
        ->and(splicedOfType($early, 'message_delta')[0])->not->toHaveKey('input_transformations');
});

test('a refusal no fallback answers carries the input transformations of the start that was not sent', function () {
    $refused = fallbackRefusedBy('claude-opus-5', ['"content":[]' => '"content":[],'.fallbackTransformations()]);
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-5', 'claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), $refused, ConnectionException::fake()]);

    $delta = splicedOfType($events, 'message_delta')[0];

    expect($delta['delta']['stop_reason'])->toBe('refusal')
        ->and($delta['input_transformations'][0]['type'])->toBe('thinking_dropped');
});

test('a fallback that refuses with no model left goes out as the last attempt, its recommended model null', function () {
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), fallbackRefusedBy('claude-opus-4-8')]);

    $delta = splicedOfType($events, 'message_delta')[0];

    expect($delta['delta']['stop_reason'])->toBe('refusal')
        ->and($delta['delta']['stop_details'])->toHaveKey('recommended_model')
        ->and($delta['delta']['stop_details']['recommended_model'])->toBeNull()
        ->and(array_map(fn (array $iteration): array => [$iteration['type'], $iteration['model']], $delta['usage']['iterations']))
        ->toBe([['message', 'claude-fable-5'], ['fallback_message', 'claude-opus-4-8']]);
});

test('a hop a retry would send again moves on, as the read can not be retried', function () {
    $sent = [];
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-5', 'claude-opus-4-8']), [fallbackSse('RefusalFallbackRefused'), new RetryableException('Overloaded'), fallbackSse('RefusalFallbackServed')], $sent);

    expect(array_column(splicedBodies($sent), 'model'))->toBe(['claude-fable-5', 'claude-opus-5', 'claude-opus-4-8']);
});

test('a stream closed partway asks no fallback', function () {
    $sent = 0;
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages', [], json_encode(fallbackRequest(['stream' => true])));
    $body = (new RefusalFallbackMiddleware(['claude-opus-4-8']))($request, function () use (&$sent): ResponseInterface {
        $sent++;

        return fallbackSse('RefusalFallbackRefused');
    })->getBody();

    $body->read(65536);
    $body->close();

    expect($sent)->toBe(1)
        ->and($body->eof())->toBeTrue();
});

test('an answer that ends right after its start still sends the start', function () {
    // composed: the served fixture's start alone
    preg_match('/^event: message_start\ndata: .+$/m', (string) fallbackSse('RefusalFallbackServed')->getBody(), $match);
    $events = splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [new Response(200, ['Content-Type' => 'text/event-stream'], $match[0]."\n\n")]);

    expect(array_column($events, 'type'))->toBe(['message_start']);
});

test('a turn with a fallback block also loses a tool call no result answers, as a stream refused mid-call leaves', function () {
    $http = new FallbackRecordingClient(fallbackJson(fallbackServed()));
    $seam = ['type' => 'fallback', 'from' => ['model' => 'claude-fable-5'], 'to' => ['model' => 'claude-opus-4-8'], 'trigger' => ['type' => 'refusal', 'category' => 'cyber']];
    // composed: the docs' tool call, cut off by a refusal, then answered in text; and one a later result answers
    $call = ['type' => 'tool_use', 'id' => 'toolu_01T1x1fJ34qAmk2tNTrN7Up6', 'name' => 'get_weather', 'input' => ['location' => 'San Francisco, CA']];
    $answered = ['type' => 'tool_use', 'id' => 'toolu_01D7FLrfh4GYq7yT1ULFeyMV', 'name' => 'get_weather', 'input' => ['location' => 'San Francisco, CA']];

    fallbackClient($http, new RefusalFallbackMiddleware(['claude-opus-4-8']))->messages()->create(fallbackRequest(['messages' => [
        ['role' => 'user', 'content' => 'Hello, Claude'],
        ['role' => 'assistant', 'content' => [$call, $seam, ['type' => 'text', 'text' => 'Hi! How can I help you today?']]],
        ['role' => 'user', 'content' => 'What is the weather in San Francisco?'],
        ['role' => 'assistant', 'content' => [$seam, $answered]],
        ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_01D7FLrfh4GYq7yT1ULFeyMV', 'content' => '15 degrees']]],
    ]]));

    expect($http->body(0)['messages'][1]['content'])->toBe([['type' => 'text', 'text' => 'Hi! How can I help you today?']])
        ->and($http->body(0)['messages'][3]['content'])->toBe([$answered]);
});

test('a raw streamed request surfaces an error the splice throws, rather than an empty body', function () {
    $http = new FallbackRecordingClient(fallbackSse('RefusalFallbackRefused'));
    $client = Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient($http)
        ->withMiddleware(new RefusalFallbackMiddleware(['claude-opus-4-8']))
        ->withMiddleware(fn (RequestInterface $request, Closure $next): ResponseInterface => str_contains((string) $request->getBody(), 'claude-opus-4-8') ? throw new RuntimeException('The token budget is used up.') : $next($request))
        ->make();

    expect(fn () => $client->request('POST', 'messages', fallbackRequest(['stream' => true])))->toThrow(RuntimeException::class, 'The token budget is used up.');
});

test('an error right after the start still sends the start, so its usage is read', function () {
    // composed: the served fixture's start, then the streaming page's error event
    preg_match('/^event: message_start\ndata: .+$/m', (string) fallbackSse('RefusalFallbackServed')->getBody(), $match);
    $failing = new Response(200, ['Content-Type' => 'text/event-stream'], $match[0]."\n\nevent: error\ndata: {\"type\": \"error\", \"error\": {\"type\": \"overloaded_error\", \"message\": \"Overloaded\"}}\n\n");

    expect(array_column(splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [$failing]), 'type'))->toBe(['message_start', 'error']);
});

test('a claim leaves out a client tool call, which nothing answers', function () {
    // composed: the refused fixture with its first block a client tool call, the docs' get_weather
    $refused = fallbackSse('RefusalFallbackRefused', ['{"type":"thinking","thinking":"","signature":""}' => '{"type":"tool_use","id":"toolu_01T1x1fJ34qAmk2tNTrN7Up6","name":"get_weather","input":{}}']);
    $sent = [];
    splicedEvents(new RefusalFallbackMiddleware(['claude-opus-4-8']), [$refused, fallbackSse('RefusalFallbackServed')], $sent);

    expect(splicedBodies($sent)[1]['messages'][1]['content'])->toBe([['type' => 'text', 'text' => 'Hi']]);
});
