<?php

declare(strict_types=1);

namespace Anthropic\Middleware;

use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\TimeoutException;
use Closure;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Traces each HTTP attempt with OpenTelemetry (`open-telemetry/api`, which the package only suggests):
 *
 * ```php
 * Anthropic::factory()->withMiddleware(new OpenTelemetryMiddleware);
 * ```
 *
 * Every attempt, a retry included, gets a client span: `anthropic.messages.create` and
 * `anthropic.messages.count_tokens` for those calls, the HTTP method for any other, with the GenAI semantic
 * conventions' attributes the official SDKs set (`gen_ai.request.model`, `gen_ai.usage.input_tokens` ...) and never a
 * prompt, a response's text or a key. The span is the current one while the request is sent, so instrumentation below
 * it nests under it, and the trace context goes out in the request's headers. A streamed response's span ends when
 * its headers arrive. A failure to record never fails the request.
 */
class OpenTelemetryMiddleware
{
    /**
     * The instrumentation scope the spans are recorded under.
     */
    public const SCOPE = 'mozex/anthropic-php';

    /**
     * @param  TracerInterface|null  $tracer  when null, the tracer provider registered at the time of each request
     * @param  TextMapPropagatorInterface|null  $propagator  when null, the propagator registered at the time of each request; `NoopTextMapPropagator::getInstance()` sends no trace context
     */
    public function __construct(
        private readonly ?TracerInterface $tracer = null,
        private readonly ?TextMapPropagatorInterface $propagator = null,
    ) {}

    /**
     * @param  Closure(RequestInterface): ResponseInterface  $next
     */
    public function __invoke(RequestInterface $request, Closure $next): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $operation = match (true) {
            str_ends_with($path, '/messages/count_tokens') => 'anthropic.messages.count_tokens',
            str_ends_with($path, '/messages') => 'anthropic.messages.create',
            default => null,
        };

        try {
            $span = ($this->tracer ?? Globals::tracerProvider()->getTracer(self::SCOPE))
                ->spanBuilder($operation ?? ($request->getMethod() !== '' ? $request->getMethod() : 'HTTP'))
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->setAttributes(self::requestAttributes($request, $operation))
                ->startSpan();
        } catch (Throwable) {
            // A sampler or a processor that throws must not fail the request.
            return $next($request);
        }

        try {
            $scope = $span->activate();
        } catch (Throwable) {
            self::quietly(fn () => $span->end());

            return $next($request);
        }

        try {
            $request = $this->withTraceContext($request);

            try {
                $response = $next($request);
            } catch (Throwable $exception) {
                // No exception event: its message can carry the request's URL, query string and all.
                self::quietly(fn () => $span->setStatus(StatusCode::STATUS_ERROR)->setAttribute('error.type', match (true) {
                    $exception instanceof TimeoutException => 'client_timeout_error',
                    $exception instanceof ConnectionException => 'connection_error',
                    default => 'client_error',
                }));

                throw $exception;
            }

            self::quietly(fn () => self::recordResponse($span, $response, $operation));

            return $response;
        } finally {
            self::quietly(fn () => $scope->detach());
            self::quietly(fn () => $span->end());
        }
    }

    /**
     * The request with the current span's trace context in its headers, written by the given propagator or the one
     * registered now.
     */
    private function withTraceContext(RequestInterface $request): RequestInterface
    {
        try {
            $carrier = [];
            ($this->propagator ?? Globals::propagator())->inject($carrier);

            foreach (is_array($carrier) ? $carrier : [] as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $request = $request->withHeader($name, $value);
                }
            }
        } catch (Throwable) {
            // The request goes out without the trace context.
        }

        return $request;
    }

    /**
     * What the span starts with: the request's method, host and path, and for a Messages call its model and settings.
     *
     * @return array<string, bool|int|float|string|list<string>>
     */
    private static function requestAttributes(RequestInterface $request, ?string $operation): array
    {
        $uri = $request->getUri();
        $body = $operation === null ? [] : self::json($request);
        $create = $operation === 'anthropic.messages.create';
        // Token counting records its model only; the settings are a create call's.
        $settings = $create ? $body : [];
        $thinking = is_array($settings['thinking'] ?? null) ? $settings['thinking'] : [];
        $outputConfig = is_array($settings['output_config'] ?? null) ? $settings['output_config'] : [];
        $betas = array_values(array_filter(array_map(trim(...), explode(',', $request->getHeaderLine('anthropic-beta'))), fn (string $beta): bool => $beta !== ''));

        $attributes = array_filter([
            'gen_ai.provider.name' => 'anthropic',
            'gen_ai.operation.name' => $create ? 'chat' : null,
            'gen_ai.request.model' => $body['model'] ?? null,
            'gen_ai.request.max_tokens' => $settings['max_tokens'] ?? null,
            'gen_ai.request.temperature' => self::double($settings['temperature'] ?? null),
            'gen_ai.request.top_p' => self::double($settings['top_p'] ?? null),
            'gen_ai.request.top_k' => $settings['top_k'] ?? null,
            'gen_ai.request.stream' => ($settings['stream'] ?? null) === true ? true : null,
            'gen_ai.request.reasoning.level' => $outputConfig['effort'] ?? null,
            'anthropic.thinking.type' => $thinking['type'] ?? null,
            'anthropic.thinking.budget_tokens' => $thinking['budget_tokens'] ?? null,
            'anthropic.request.service_tier' => $settings['service_tier'] ?? null,
            'http.request.method' => $request->getMethod(),
            'server.address' => trim($uri->getHost(), '[]'),
            'server.port' => $uri->getPort() ?? ($uri->getScheme() === 'http' ? 80 : 443),
            'url.scheme' => $uri->getScheme(),
            'url.path' => $uri->getPath(),
        ], fn (mixed $value): bool => is_bool($value) || is_int($value) || is_float($value) || (is_string($value) && $value !== ''));

        if ($betas !== []) {
            $attributes['anthropic.beta'] = $betas;
        }

        /** @var array<string, bool|int|float|string|list<string>> */
        return $attributes;
    }

    /**
     * Records the status, the IDs the headers carry and, on a Messages answer that isn't a stream, its ID, model, stop
     * reason and token counts. A status of 400 or more sets the span's status to an error, of the API's error type.
     */
    private static function recordResponse(SpanInterface $span, ResponseInterface $response, ?string $operation): void
    {
        $status = $response->getStatusCode();
        $span->setAttribute('http.response.status_code', $status);

        foreach (['anthropic.request.id' => 'request-id', 'anthropic.organization.id' => 'anthropic-organization-id', 'anthropic.workspace.id' => 'anthropic-workspace-id'] as $attribute => $header) {
            $value = $response->getHeaderLine($header);

            if ($value !== '') {
                $span->setAttribute($attribute, $value);
            }
        }

        // Only a JSON body the span uses is read: a download, a stream or a JSONL answer is left alone.
        $mediaType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
        $json = $mediaType === 'application/json' || str_ends_with($mediaType, '+json');
        $body = $json && ($status >= 400 || $operation !== null) ? self::json($response) : [];

        if ($status >= 400) {
            $error = is_array($body['error'] ?? null) ? $body['error'] : [];
            $span->setStatus(StatusCode::STATUS_ERROR);
            $span->setAttribute('error.type', is_string($error['type'] ?? null) && $error['type'] !== '' ? $error['type'] : (string) $status);

            return;
        }

        $span->setStatus(StatusCode::STATUS_OK);

        if ($operation === 'anthropic.messages.count_tokens') {
            if (is_int($body['input_tokens'] ?? null)) {
                $span->setAttribute('anthropic.message.count_tokens.input_tokens', $body['input_tokens']);
            }

            return;
        }

        if ($operation !== 'anthropic.messages.create') {
            return;
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
        $cacheRead = is_int($usage['cache_read_input_tokens'] ?? null) ? $usage['cache_read_input_tokens'] : 0;
        $cacheWrite = is_int($usage['cache_creation_input_tokens'] ?? null) ? $usage['cache_creation_input_tokens'] : 0;

        foreach ([
            'gen_ai.response.id' => $body['id'] ?? null,
            'gen_ai.response.model' => $body['model'] ?? null,
            'anthropic.message.stop_reason' => $body['stop_reason'] ?? null,
            'anthropic.response.service_tier' => $usage['service_tier'] ?? null,
            // The conventions count every input token, the cached ones included, as the official SDKs do.
            'gen_ai.usage.input_tokens' => is_int($usage['input_tokens'] ?? null) ? $usage['input_tokens'] + $cacheRead + $cacheWrite : null,
            'gen_ai.usage.output_tokens' => $usage['output_tokens'] ?? null,
            'gen_ai.usage.cache_read.input_tokens' => $usage['cache_read_input_tokens'] ?? null,
            'gen_ai.usage.cache_write.input_tokens' => $usage['cache_creation_input_tokens'] ?? null,
        ] as $attribute => $value) {
            if (is_string($value) || is_int($value)) {
                $span->setAttribute($attribute, $value);
            }
        }
    }

    /**
     * A number as the double an attribute of that convention holds.
     */
    private static function double(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * Runs a telemetry call whose failure must not reach the request.
     */
    private static function quietly(Closure $call): void
    {
        try {
            $call();
        } catch (Throwable) {
            //
        }
    }

    /**
     * A message's JSON body as an array, its stream rewound for whoever reads it next; empty when it can't be read
     * twice or isn't a JSON object or list.
     *
     * @return array<array-key, mixed>
     */
    private static function json(MessageInterface $message): array
    {
        $stream = $message->getBody();

        if (! $stream->isSeekable()) {
            return [];
        }

        $stream->rewind();
        $decoded = json_decode($stream->getContents(), true);
        $stream->rewind();

        return is_array($decoded) ? $decoded : [];
    }
}
