<?php

declare(strict_types=1);

use Anthropic\Exceptions\BadRequestException;
use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Exceptions\RetryableException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Factory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;

/**
 * A factory over a mocked Guzzle client that records every request it sends and every wait between attempts.
 *
 * @param  array<int, array{request: RequestInterface}>  $history
 * @param  list<int>  $sleeps
 */
function retriesFactory(array &$history, array &$sleeps, Response|Throwable ...$queue): Factory
{
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($history));

    return Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient(new GuzzleClient(['handler' => $stack]))
        ->withRetrySleep(function (int $milliseconds) use (&$sleeps): void {
            $sleeps[] = $milliseconds;
        });
}

/**
 * The errors page's JSON, with its status and type per case. Composed: every type shares the overloaded example's
 * message, which no test reads.
 */
function retriesError(int $status, string $type, array $headers = []): Response
{
    return new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode([
        'type' => 'error',
        'error' => ['type' => $type, 'message' => 'Overloaded'],
        'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
    ]));
}

function retriesModel(): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode(modelRetrieve()));
}

/**
 * A PSR-18 client that answers from a queue and records each request's body from where the stream stands, as a
 * client sending it would read it.
 */
class RetriesRecordingClient implements ClientInterface
{
    /** @var list<string> */
    public array $bodies = [];

    /** @var list<ResponseInterface> */
    private array $responses;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->bodies[] = $request->getBody()->getContents();

        return array_shift($this->responses);
    }
}

class RetriesLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message, $context];
    }
}

function retriesRecordingFactory(RetriesRecordingClient $client): Factory
{
    return Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient($client)
        ->withRetrySleep(fn (int $milliseconds) => null);
}

test('requests are not retried by default', function () {
    $history = $sleeps = [];
    $client = retriesFactory($history, $sleeps, retriesError(529, 'overloaded_error'), retriesModel())->make();

    expect(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(OverloadedException::class)
        ->and($history)->toHaveCount(1)
        ->and($sleeps)->toBe([]);
});

test('a retryable status is sent again with a backoff that doubles, less up to a quarter', function () {
    $history = $sleeps = [];
    $client = retriesFactory($history, $sleeps, retriesError(529, 'overloaded_error'), retriesError(529, 'overloaded_error'), retriesModel())
        ->withMaxRetries(2)
        ->make();

    expect($client->models()->retrieve('claude-sonnet-5-5')->id)->toBe('claude-sonnet-4-6')
        ->and($history)->toHaveCount(3)
        ->and($sleeps[0])->toBeGreaterThanOrEqual(375)->toBeLessThanOrEqual(500)
        ->and($sleeps[1])->toBeGreaterThanOrEqual(750)->toBeLessThanOrEqual(1000);
});

test('the statuses worth retrying are retried and the others are not', function (int $status, string $type, bool $retried) {
    $history = $sleeps = [];
    $client = retriesFactory($history, $sleeps, retriesError($status, $type), retriesModel())->withMaxRetries(1)->make();

    try {
        $client->models()->retrieve('claude-sonnet-5-5');
    } catch (ErrorException) {
        //
    }

    expect($history)->toHaveCount($retried ? 2 : 1);
})->with([
    // composed: 408, 409 and 502 come with these types on no docs page
    '408' => [408, 'timeout_error', true],
    '409' => [409, 'conflict_error', true],
    '429' => [429, 'rate_limit_error', true],
    '500' => [500, 'api_error', true],
    '502' => [502, 'api_error', true],
    '504' => [504, 'timeout_error', true],
    '529' => [529, 'overloaded_error', true],
    '400' => [400, 'invalid_request_error', false],
    '401' => [401, 'authentication_error', false],
    '403' => [403, 'permission_error', false],
    '404' => [404, 'not_found_error', false],
    '413' => [413, 'request_too_large', false],
]);

test('x-should-retry overrides the status', function () {
    $history = $sleeps = [];
    // composed: no docs example shows the header's value
    $client = retriesFactory(
        $history,
        $sleeps,
        retriesError(400, 'invalid_request_error', ['x-should-retry' => 'true']),
        retriesModel(),
        retriesError(529, 'overloaded_error', ['x-should-retry' => 'false']),
    )->withMaxRetries(1)->make();

    expect($client->models()->retrieve('claude-sonnet-5-5'))->not->toBeNull()
        ->and(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(OverloadedException::class)
        ->and($history)->toHaveCount(3);
});

test('a success is never sent again, whatever x-should-retry says', function () {
    $history = $sleeps = [];
    // composed: no docs example shows the header's value
    $client = retriesFactory($history, $sleeps, new Response(200, ['Content-Type' => 'application/json', 'x-should-retry' => 'true'], json_encode(messagesCompletion())), retriesModel())
        ->withMaxRetries(1)
        ->make();

    $client->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => [['role' => 'user', 'content' => 'Hello']]]);

    expect($history)->toHaveCount(1)
        ->and($sleeps)->toBe([]);
});

test('the delay follows retry-after-ms, then retry-after, and the backoff when neither asks for a wait', function (array $headers, ?int $expected) {
    $history = $sleeps = [];
    $client = retriesFactory($history, $sleeps, retriesError(429, 'rate_limit_error', $headers), retriesModel())->withMaxRetries(1)->make();

    $client->models()->retrieve('claude-sonnet-5-5');

    if ($expected === null) {
        expect($sleeps[0])->toBeGreaterThanOrEqual(375)->toBeLessThanOrEqual(500);
    } else {
        expect($sleeps)->toBe([$expected]);
    }
})->with([
    'retry-after-ms' => [['retry-after-ms' => '1500', 'retry-after' => '30'], 1500],
    'retry-after seconds' => [['retry-after' => '2'], 2000],
    'over a minute' => [['retry-after' => '90'], 90000],
    'zero falls back to the backoff' => [['retry-after' => '0'], null],
    'not a number falls back to the backoff' => [['retry-after-ms' => 'soon'], null],
    'a date in the past falls back to the backoff' => [['retry-after' => 'Wed, 21 Oct 2015 07:28:00 GMT'], null],
    'too long to sleep falls back to the backoff' => [['retry-after-ms' => '1e30'], null],
]);

test('when the retries run out the last error is thrown', function () {
    $history = $sleeps = [];
    $client = retriesFactory($history, $sleeps, retriesError(529, 'overloaded_error'), retriesError(529, 'overloaded_error'), retriesError(529, 'overloaded_error'))
        ->withMaxRetries(2)
        ->make();

    expect(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(OverloadedException::class)
        ->and($history)->toHaveCount(3)
        ->and($sleeps)->toHaveCount(2);
});

test('a connection failure is retried, any other client failure is not', function () {
    $history = $sleeps = [];
    $request = new Request('GET', 'https://api.anthropic.com/v1/models/claude-sonnet-5-5');
    $client = retriesFactory(
        $history,
        $sleeps,
        new ConnectException('cURL error 7: Failed to connect to api.anthropic.com port 443', $request),
        retriesModel(),
        new TransferException('Something went wrong with the transfer'),
        retriesModel(),
    )->withMaxRetries(1)->make();

    expect($client->models()->retrieve('claude-sonnet-5-5'))->not->toBeNull()
        ->and(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(TransporterException::class)
        // the failed connection, its retry, and the transfer failure, which isn't retried
        ->and($history)->toHaveCount(3);
});

test('a connection failure that keeps failing throws once the retries run out', function () {
    $history = $sleeps = [];
    $request = new Request('GET', 'https://api.anthropic.com/v1/models/claude-sonnet-5-5');
    $client = retriesFactory(
        $history,
        $sleeps,
        new ConnectException('cURL error 7: Failed to connect', $request),
        new ConnectException('cURL error 7: Failed to connect', $request),
    )->withMaxRetries(1)->make();

    expect(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(ConnectionException::class)
        ->and($sleeps)->toHaveCount(1);
});

test('an upload is sent whole again, since the multipart body is buffered', function () {
    $http = new RetriesRecordingClient(retriesError(529, 'overloaded_error'), new Response(200, ['Content-Type' => 'application/json'], json_encode(fileResponse())));
    $client = retriesRecordingFactory($http)->withMaxRetries(1)->make();

    $client->files()->upload(['file' => new NoSeekStream(Utils::streamFor('%PDF-1.4'))]);

    expect($http->bodies)->toHaveCount(2)
        ->and($http->bodies[0])->toContain('%PDF-1.4')
        ->and($http->bodies[1])->toBe($http->bodies[0]);
});

test('a json body is sent whole on every attempt', function () {
    $http = new RetriesRecordingClient(retriesError(529, 'overloaded_error'), new Response(200, ['Content-Type' => 'application/json'], json_encode(messagesCompletion())));
    $client = retriesRecordingFactory($http)->withMaxRetries(1)->make();

    $client->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => [['role' => 'user', 'content' => 'Hello']]]);

    expect($http->bodies[0])->toContain('"max_tokens":10')
        ->and($http->bodies[1])->toBe($http->bodies[0]);
});

test('a response that is retried is closed before the wait', function () {
    $error = retriesError(529, 'overloaded_error');
    $client = retriesRecordingFactory(new RetriesRecordingClient($error, retriesModel()))->withMaxRetries(1)->make();

    $client->models()->retrieve('claude-sonnet-5-5');

    expect($error->getBody()->isReadable())->toBeFalse();
});

test('a stream is retried before it starts, never once it has', function () {
    $history = $sleeps = [];
    $client = retriesFactory(
        $history,
        $sleeps,
        retriesError(529, 'overloaded_error'),
        new Response(200, ['Content-Type' => 'text/event-stream'], messagesCompletionStreamError()),
    )->withMaxRetries(3)->make();

    $stream = $client->messages()->createStreamed(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => [['role' => 'user', 'content' => 'Hello']]]);

    expect(fn () => iterator_to_array($stream))->toThrow(ErrorException::class)
        ->and($history)->toHaveCount(2);
});

test('a middleware runs around every attempt, the first one added outermost', function () {
    $history = $sleeps = $order = [];
    $client = retriesFactory($history, $sleeps, retriesError(529, 'overloaded_error'), retriesModel())
        ->withMaxRetries(1)
        ->withMiddleware(function (RequestInterface $request, Closure $next) use (&$order): ResponseInterface {
            $order[] = 'outer';

            return $next($request->withHeader('X-Attempt', (string) count($order)));
        })
        ->withMiddleware(function (RequestInterface $request, Closure $next) use (&$order): ResponseInterface {
            $order[] = 'inner';

            return $next($request);
        })
        ->make();

    $client->models()->retrieve('claude-sonnet-5-5');

    expect($order)->toBe(['outer', 'inner', 'outer', 'inner'])
        ->and($history[0]['request']->getHeaderLine('X-Attempt'))->toBe('1')
        ->and($history[1]['request']->getHeaderLine('X-Attempt'))->toBe('3');
});

test('a middleware sees an error response before it is thrown', function () {
    $history = $sleeps = $statuses = [];
    $client = retriesFactory($history, $sleeps, retriesError(400, 'invalid_request_error'), retriesError(529, 'overloaded_error'))
        ->withMiddleware(function (RequestInterface $request, Closure $next) use (&$statuses): ResponseInterface {
            $response = $next($request);
            $statuses[] = $response->getStatusCode();

            return $response;
        })
        ->make();

    expect(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(BadRequestException::class)
        // a streamed request's error, which Guzzle throws as an exception, reaches the middleware as a response too
        ->and(fn () => $client->messages()->createStreamed(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => [['role' => 'user', 'content' => 'Hello']]]))->toThrow(OverloadedException::class)
        ->and($statuses)->toBe([400, 529]);
});

test('a middleware can force a retry', function () {
    $history = $sleeps = $statuses = [];
    $client = retriesFactory($history, $sleeps, retriesModel(), retriesModel())
        ->withMaxRetries(1)
        ->withMiddleware(function (RequestInterface $request, Closure $next) use (&$statuses): ResponseInterface {
            $response = $next($request);
            $statuses[] = $response->getStatusCode();

            if (count($statuses) === 1) {
                throw new RetryableException('Stale cache, try again');
            }

            return $response;
        })
        ->make();

    expect($client->models()->retrieve('claude-sonnet-5-5'))->not->toBeNull()
        ->and($statuses)->toBe([200, 200])
        ->and($history)->toHaveCount(2);
});

test('a middleware can be an invokable object', function () {
    $history = $sleeps = [];
    $middleware = new class
    {
        public int $calls = 0;

        public function __invoke(RequestInterface $request, Closure $next): ResponseInterface
        {
            $this->calls++;

            return $next($request);
        }
    };

    retriesFactory($history, $sleeps, retriesModel())->withMiddleware($middleware)->make()->models()->retrieve('claude-sonnet-5-5');

    expect($middleware->calls)->toBe(1);
});

test('a forced retry past the limit reaches the caller', function () {
    $history = $sleeps = [];
    $client = retriesFactory($history, $sleeps, retriesModel())
        ->withMiddleware(fn (RequestInterface $request, Closure $next): ResponseInterface => throw new RetryableException('Never good enough'))
        ->make();

    expect(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(RetryableException::class, 'Never good enough');
});

test('the logger gets each attempt and retry, never a key, a query or a body', function () {
    $history = $sleeps = [];
    $logger = new RetriesLogger;

    $client = retriesFactory(
        $history,
        $sleeps,
        retriesError(529, 'overloaded_error', ['request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG']),
        new Response(200, ['Content-Type' => 'application/json'], json_encode(messagesCompletion())),
    )
        ->withMaxRetries(1)
        ->withLogger($logger)
        // a gateway that takes its key in the query string
        ->withQueryParam('gateway_key', 'gw-secret')
        ->make();

    $client->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 10, 'messages' => [['role' => 'user', 'content' => 'a secret prompt']]]);

    $messages = array_map(fn (array $record): string => $record[0].' '.$record[1], $logger->records);
    $serialized = json_encode($logger->records);

    expect($messages)->toBe([
        'info Anthropic request',
        'info Anthropic response',
        'warning Retrying Anthropic request',
        'info Anthropic request',
        'info Anthropic response',
    ])
        ->and($logger->records[1][2]['status'])->toBe(529)
        ->and($logger->records[1][2]['request_id'])->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        ->and($logger->records[0][2]['uri'])->toBe('https://api.anthropic.com/v1/messages')
        ->and($logger->records[2][2]['reason'])->toBe('status 529')
        ->and($serialized)->not->toContain('foo')
        ->and($serialized)->not->toContain('gw-secret')
        ->and($serialized)->not->toContain('a secret prompt');
});

test('options set the retries for one client, which keeps the middleware, the logger and the sleep', function () {
    $history = $sleeps = [];
    $attempts = 0;
    $logger = new RetriesLogger;
    $client = retriesFactory($history, $sleeps, retriesError(529, 'overloaded_error'), retriesModel(), retriesError(529, 'overloaded_error'))
        ->withMiddleware(function (RequestInterface $request, Closure $next) use (&$attempts): ResponseInterface {
            $attempts++;

            return $next($request);
        })
        ->withLogger($logger)
        ->make();

    expect($client->withOptions(['max_retries' => 1])->models()->retrieve('claude-sonnet-5-5'))->not->toBeNull()
        ->and($attempts)->toBe(2)
        ->and($sleeps)->toHaveCount(1)
        ->and($logger->records)->toHaveCount(5)
        // the client it was called on still makes one attempt
        ->and(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(OverloadedException::class)
        ->and($history)->toHaveCount(3);
});

test('a negative retry count is rejected', function () {
    expect(fn () => Anthropic::factory()->withMaxRetries(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Anthropic::client('foo')->withOptions(['max_retries' => -1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Anthropic::client('foo')->withOptions(['max_retries' => '2']))->toThrow(InvalidArgumentException::class);
});

test('a bad request is not retried even with retries left', function () {
    $history = $sleeps = [];
    $client = retriesFactory($history, $sleeps, retriesError(400, 'invalid_request_error'))->withMaxRetries(3)->make();

    expect(fn () => $client->models()->retrieve('claude-sonnet-5-5'))->toThrow(BadRequestException::class)
        ->and($sleeps)->toBe([]);
});
