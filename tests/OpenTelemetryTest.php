<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Exceptions\TimeoutException;
use Anthropic\Middleware\OpenTelemetryMiddleware;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A client whose requests go through the middleware, recording spans in the exporter and the requests in the history.
 *
 * @param  array<int, array{request: RequestInterface}>  $history
 */
function tracedClient(InMemoryExporter $exporter, array &$history, array $queue, ?OpenTelemetryMiddleware $middleware = null, int $maxRetries = 0, ?Closure $inner = null): Client
{
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($history));
    $tracer = (new TracerProvider(new SimpleSpanProcessor($exporter)))->getTracer('test');

    $factory = Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient(new GuzzleClient(['handler' => $stack]))
        ->withMiddleware($middleware ?? new OpenTelemetryMiddleware($tracer, TraceContextPropagator::getInstance()))
        ->withMaxRetries($maxRetries)
        ->withRetrySleep(fn (int $milliseconds) => null);

    return ($inner === null ? $factory : $factory->withMiddleware($inner))->make();
}

/**
 * @return list<ImmutableSpan>
 */
function tracedSpans(InMemoryExporter $exporter): array
{
    /** @var list<ImmutableSpan> */
    return array_values(iterator_to_array($exporter->getSpans()));
}

function tracedJson(array $body, int $status = 200, array $headers = []): Response
{
    return new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($body));
}

test('a message gets a client span with the request and the answer, and nothing of the prompt', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $client = tracedClient($exporter, $history, [tracedJson(messagesCompletion(), headers: ['request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'])]);

    $client->messages()->create([
        'model' => 'claude-opus-5-5',
        'max_tokens' => 1024,
        'temperature' => 1,
        'thinking' => ['type' => 'enabled', 'budget_tokens' => 2048],
        'output_config' => ['effort' => 'high'],
        'messages' => [['role' => 'user', 'content' => 'a secret prompt']],
        'betas' => ['compact-2026-09-04'],
    ]);

    [$span] = tracedSpans($exporter);
    $attributes = $span->getAttributes()->toArray();

    expect($span->getName())->toBe('anthropic.messages.create')
        ->and($span->getKind())->toBe(SpanKind::KIND_CLIENT)
        ->and($span->getStatus()->getCode())->toBe(StatusCode::STATUS_OK)
        ->and($attributes)->toMatchArray([
            'gen_ai.provider.name' => 'anthropic',
            'gen_ai.operation.name' => 'chat',
            'gen_ai.request.model' => 'claude-opus-5-5',
            'gen_ai.request.max_tokens' => 1024,
            'gen_ai.request.temperature' => 1.0,
            'gen_ai.request.reasoning.level' => 'high',
            'anthropic.thinking.type' => 'enabled',
            'anthropic.thinking.budget_tokens' => 2048,
            'anthropic.beta' => ['compact-2026-09-04'],
            'http.request.method' => 'POST',
            'server.address' => 'api.anthropic.com',
            'server.port' => 443,
            'url.path' => '/v1/messages',
            'http.response.status_code' => 200,
            'anthropic.request.id' => 'req_018EeWyXxfu5pfWkrYcMdjWG',
            'gen_ai.response.id' => messagesCompletion()['id'],
            'gen_ai.response.model' => messagesCompletion()['model'],
            'anthropic.message.stop_reason' => messagesCompletion()['stop_reason'],
            'gen_ai.usage.input_tokens' => messagesCompletion()['usage']['input_tokens'],
            'gen_ai.usage.output_tokens' => messagesCompletion()['usage']['output_tokens'],
        ])
        ->and(json_encode($attributes))->not->toContain('a secret prompt')
        ->and(json_encode($attributes))->not->toContain('foo');
});

test('the trace context goes out with the request, from the span that traces it', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $client = tracedClient($exporter, $history, [tracedJson(modelRetrieve())]);

    $client->models()->retrieve('claude-sonnet-4-6');

    [$span] = tracedSpans($exporter);

    expect($history[0]['request']->getHeaderLine('traceparent'))
        ->toBe(sprintf('00-%s-%s-%02x', $span->getTraceId(), $span->getSpanId(), $span->getContext()->getTraceFlags()))
        ->and($span->getContext()->isSampled())->toBeTrue();
});

test('no trace context is sent with a propagator that sends none', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $tracer = (new TracerProvider(new SimpleSpanProcessor($exporter)))->getTracer('test');
    $client = tracedClient($exporter, $history, [tracedJson(modelRetrieve())], new OpenTelemetryMiddleware($tracer, NoopTextMapPropagator::getInstance()));

    $client->models()->retrieve('claude-sonnet-4-6');

    expect($history[0]['request']->hasHeader('traceparent'))->toBeFalse()
        ->and(tracedSpans($exporter))->toHaveCount(1);
});

test('another endpoint gets a span named after its method', function () {
    $exporter = new InMemoryExporter;
    $history = [];

    tracedClient($exporter, $history, [tracedJson(modelRetrieve())])->models()->retrieve('claude-sonnet-4-6');

    [$span] = tracedSpans($exporter);

    expect($span->getName())->toBe('GET')
        ->and($span->getAttributes()->get('url.path'))->toBe('/v1/models/claude-sonnet-4-6')
        ->and($span->getAttributes()->has('gen_ai.operation.name'))->toBeFalse();
});

test('token counting records the count', function () {
    $exporter = new InMemoryExporter;
    $history = [];

    tracedClient($exporter, $history, [tracedJson(messagesCountTokens())])->messages()->countTokens([
        'model' => 'claude-opus-5-5',
        'messages' => [['role' => 'user', 'content' => 'Hello, world']],
    ]);

    [$span] = tracedSpans($exporter);

    expect($span->getName())->toBe('anthropic.messages.count_tokens')
        ->and($span->getAttributes()->get('anthropic.message.count_tokens.input_tokens'))->toBe(messagesCountTokens()['input_tokens']);
});

test('an error response marks the span with the API error type, and each retry gets its own span', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    // composed: the overloaded error, as tests/Exceptions/TypedErrorsTest.php composes it
    $overloaded = fn (): Response => tracedJson(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded'], 'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy'], 529);
    $client = tracedClient($exporter, $history, [$overloaded(), tracedJson(modelRetrieve())], maxRetries: 1);

    $client->models()->retrieve('claude-sonnet-4-6');

    [$failed, $served] = tracedSpans($exporter);

    expect($failed->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($failed->getAttributes()->get('error.type'))->toBe('overloaded_error')
        ->and($failed->getAttributes()->get('http.response.status_code'))->toBe(529)
        ->and($served->getStatus()->getCode())->toBe(StatusCode::STATUS_OK)
        // the retry's trace context names its own span
        ->and($history[1]['request']->getHeaderLine('traceparent'))->toContain($served->getSpanId());

    $exporter = new InMemoryExporter;
    $history = [];

    expect(fn () => tracedClient($exporter, $history, [$overloaded()])->models()->retrieve('claude-sonnet-4-6'))->toThrow(OverloadedException::class);
});

test('a connection failure ends the span with the error', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $request = new Request('GET', 'https://api.anthropic.com/v1/models/claude-sonnet-4-6');
    $client = tracedClient($exporter, $history, [new ConnectException('cURL error 7: Failed to connect to api.anthropic.com port 443', $request)]);

    expect(fn () => $client->models()->retrieve('claude-sonnet-4-6'))->toThrow(ConnectionException::class);

    [$span] = tracedSpans($exporter);

    // no exception event: its message can carry the URL's query string
    expect($span->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($span->getAttributes()->get('error.type'))->toBe('connection_error')
        ->and($span->getEvents())->toHaveCount(0);
});

test('a timeout ends the span as a client timeout', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $request = new Request('GET', 'https://api.anthropic.com/v1/models/claude-sonnet-4-6');
    $client = tracedClient($exporter, $history, [new ConnectException('cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received', $request)]);

    expect(fn () => $client->models()->retrieve('claude-sonnet-4-6'))->toThrow(TimeoutException::class);

    expect(tracedSpans($exporter)[0]->getAttributes()->get('error.type'))->toBe('client_timeout_error');
});

test('the span is the current one while the request goes out, so what runs below nests under it', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $current = null;
    $inner = function (RequestInterface $request, Closure $next) use (&$current): ResponseInterface {
        $current = Span::getCurrent()->getContext()->getSpanId();

        return $next($request);
    };

    tracedClient($exporter, $history, [tracedJson(modelRetrieve())], inner: $inner)->models()->retrieve('claude-sonnet-4-6');

    expect($current)->toBe(tracedSpans($exporter)[0]->getSpanId())
        // and it's no longer current once the call is done
        ->and(Span::getCurrent()->getContext()->isValid())->toBeFalse();
});

test('input tokens count the cached ones too, as the conventions do', function () {
    $exporter = new InMemoryExporter;
    $history = [];

    tracedClient($exporter, $history, [tracedJson(messagesCompletionWithCache())])->messages()->create(['model' => 'claude-sonnet-4-6', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello']]]);

    $attributes = tracedSpans($exporter)[0]->getAttributes();

    expect($attributes->get('gen_ai.usage.input_tokens'))->toBe(80)
        ->and($attributes->get('gen_ai.usage.cache_read.input_tokens'))->toBe(40)
        ->and($attributes->get('gen_ai.usage.cache_write.input_tokens'))->toBe(30);
});

test('a provider registered after the client was built still gets the spans', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $client = tracedClient($exporter, $history, [tracedJson(modelRetrieve())], new OpenTelemetryMiddleware);

    $scope = Configurator::create()
        ->withTracerProvider(new TracerProvider(new SimpleSpanProcessor($exporter)))
        ->withPropagator(TraceContextPropagator::getInstance())
        ->activate();

    try {
        $client->models()->retrieve('claude-sonnet-4-6');
    } finally {
        $scope->detach();
    }

    expect(tracedSpans($exporter))->toHaveCount(1)
        ->and($history[0]['request']->getHeaderLine('traceparent'))->toContain(tracedSpans($exporter)[0]->getTraceId());
});

test('the IDs the headers carry are recorded, and an error without a JSON body is typed by its status', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    // composed: the organization ID MetaInformationTest uses, the docs' workspace ID, a gateway's plain-text error
    $client = tracedClient($exporter, $history, [
        tracedJson(modelRetrieve(), headers: ['anthropic-organization-id' => '8a3c3a4e-3f3b-4d2a-9c41-5f4e1b2a7c90', 'anthropic-workspace-id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy']),
        new Response(502, ['Content-Type' => 'text/html'], '<html>Bad Gateway</html>'),
    ]);

    $client->models()->retrieve('claude-sonnet-4-6');

    try {
        $client->models()->retrieve('claude-sonnet-4-6');
    } catch (ErrorException) {
        //
    }

    [$served, $failed] = tracedSpans($exporter);

    expect($served->getAttributes()->get('anthropic.organization.id'))->toBe('8a3c3a4e-3f3b-4d2a-9c41-5f4e1b2a7c90')
        ->and($served->getAttributes()->get('anthropic.workspace.id'))->toBe('wrkspc_011CZkZaBF1tNoB5wlCeusgy')
        ->and($failed->getAttributes()->get('error.type'))->toBe('502');
});

test('a streamed message gets its span when the headers arrive, and the stream is left whole', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $client = tracedClient($exporter, $history, [new Response(200, ['Content-Type' => 'text/event-stream'], messagesCompletionStream())]);

    $stream = $client->messages()->createStreamed(['model' => 'claude-opus-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello']]]);

    [$span] = tracedSpans($exporter);

    expect($span->getAttributes()->get('gen_ai.request.stream'))->toBeTrue()
        ->and($span->getAttributes()->has('gen_ai.response.id'))->toBeFalse()
        ->and(iterator_to_array($stream))->not->toBe([]);
});

test('the request body still reaches the API after the span reads it', function () {
    $exporter = new InMemoryExporter;
    $history = [];
    $parameters = ['model' => 'claude-opus-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello']]];

    tracedClient($exporter, $history, [tracedJson(messagesCompletion())])->messages()->create($parameters);

    // read from where the stream stands, as a client that doesn't rewind would send it
    expect($history[0]['request']->getBody()->tell())->toBe(0)
        ->and($history[0]['request']->getBody()->getContents())->toBe(json_encode($parameters));
});
