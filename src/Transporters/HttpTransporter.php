<?php

declare(strict_types=1);

namespace Anthropic\Transporters;

use Anthropic\Contracts\TransporterContract;
use Anthropic\Enums\Transporter\ContentType;
use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\RetryableException;
use Anthropic\Exceptions\TimeoutException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnserializableResponse;
use Anthropic\ValueObjects\ClientSettings;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;
use Anthropic\ValueObjects\Transporter\RequestOptions;
use Anthropic\ValueObjects\Transporter\Response;
use Closure;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Throwable;

/**
 * @internal
 */
class HttpTransporter implements TransporterContract
{
    /**
     * Creates a new Http Transporter instance.
     *
     * @param  Closure(RequestInterface): ResponseInterface  $streamHandler
     * @param  list<Closure(RequestInterface, Closure(RequestInterface): ResponseInterface): ResponseInterface>  $middleware  run once per attempt, the first one outermost
     * @param  (Closure(int): void)|null  $sleep  waits the given milliseconds between attempts
     * @param  float|null  $timeout  the timeout of the HTTP client the factory built, for settings() only
     * @param  float|null  $connectTimeout  its connection timeout, for settings() only
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly BaseUri $baseUri,
        private Headers $headers,
        private readonly QueryParams $queryParams,
        private readonly Closure $streamHandler,
        private readonly int $maxRetries = 0,
        private readonly array $middleware = [],
        private readonly ?LoggerInterface $logger = null,
        private readonly ?Closure $sleep = null,
        private readonly ?float $timeout = null,
        private readonly ?float $connectTimeout = null,
    ) {
        // ..
    }

    /**
     * {@inheritDoc}
     */
    public function requestObject(Payload $payload): Response
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->send($request, $this->client->sendRequest(...));

        $contents = (string) $response->getBody();

        $this->throwIfError($response, $contents);

        if (str_contains($response->getHeaderLine('Content-Type'), ContentType::TEXT_PLAIN->value)) {
            return Response::from($contents, $response->getHeaders());
        }

        try {
            /** @var array{error?: array{message: string, type: string}} $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new UnserializableResponse($jsonException, $response);
        }

        return Response::from($data, $response->getHeaders());
    }

    /**
     * {@inheritDoc}
     */
    public function requestContent(Payload $payload): string
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->send($request, $this->client->sendRequest(...));

        $contents = (string) $response->getBody();

        $this->throwIfError($response, $contents);

        return $contents;
    }

    /**
     * {@inheritDoc}
     */
    public function requestRaw(Payload $payload): Response
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->send($request, $this->client->sendRequest(...));

        $contents = (string) $response->getBody();

        $this->throwIfError($response, $contents);

        // A body that isn't a JSON object or array, a 204's empty one and JSONL included, stays a string.
        $mediaType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
        $isJson = $mediaType === 'application/json' || str_ends_with($mediaType, '+json');
        $data = $isJson ? json_decode($contents, true) : null;

        return Response::from(is_array($data) ? $data : $contents, $response->getHeaders(), $response->getStatusCode());
    }

    /**
     * {@inheritDoc}
     */
    public function requestStream(Payload $payload): ResponseInterface
    {
        $request = $payload->toRequest($this->baseUri, $this->headers, $this->queryParams);

        $response = $this->send($request, $this->streamHandler);

        $this->throwIfError($response);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers = $this->headers->withCustomHeader($name, $value);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function withOptions(#[SensitiveParameter] array $options): self
    {
        $options = RequestOptions::from($options);

        return new self(
            $this->client,
            $this->baseUri,
            $options->applyToHeaders($this->headers),
            $options->applyToQueryParams($this->queryParams),
            $this->streamHandler,
            $options->maxRetries ?? $this->maxRetries,
            $this->middleware,
            $this->logger,
            $this->sleep,
            $this->timeout,
            $this->connectTimeout,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function settings(): ClientSettings
    {
        $headers = array_change_key_case($this->headers->toArray(), CASE_LOWER);
        $betas = array_values(array_filter(array_map(trim(...), explode(',', $headers['anthropic-beta'] ?? '')), fn (string $beta): bool => $beta !== ''));

        return new ClientSettings(
            // Without user info, where a proxy's credentials may ride.
            (string) preg_replace('#^([a-z][a-z0-9+.-]*://)[^/?\#]*@#i', '$1', $this->baseUri->toString()),
            $headers['anthropic-version'] ?? '',
            $betas,
            $headers['anthropic-workspace-id'] ?? null,
            $this->maxRetries,
            $this->timeout,
            $this->connectTimeout,
        );
    }

    /**
     * Sends the request through the middleware, retrying a connection failure, a RetryableException and the
     * statuses the API asks to retry, up to the retry limit.
     *
     * @param  Closure(RequestInterface): ResponseInterface  $handler
     */
    private function send(RequestInterface $request, Closure $handler): ResponseInterface
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn (Closure $next, Closure $middleware): Closure => fn (RequestInterface $request): ResponseInterface => $middleware($request, $next),
            fn (RequestInterface $request): ResponseInterface => $this->sendOnce($request, $handler),
        );

        for ($attempt = 0; ; $attempt++) {
            $started = hrtime(true);

            $this->logger?->info('Anthropic request', ['method' => $request->getMethod(), 'uri' => $this->loggedUri($request), 'attempt' => $attempt + 1]);

            try {
                $response = $pipeline($request);
            } catch (ConnectionException|RetryableException $exception) {
                if (! $this->canRetry($attempt, $request)) {
                    throw $exception;
                }

                $this->wait($attempt, $exception);

                continue;
            }

            $this->logger?->info('Anthropic response', [
                'status' => $response->getStatusCode(),
                'request_id' => $response->getHeaderLine('request-id'),
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);

            if (! $this->shouldRetry($response) || ! $this->canRetry($attempt, $request)) {
                return $response;
            }

            // A streamed response holds its connection open until its body is closed.
            $response->getBody()->close();

            $this->wait($attempt, $response);
        }
    }

    /**
     * One attempt: a response for every HTTP status, an exception only when no response came back.
     *
     * @param  Closure(RequestInterface): ResponseInterface  $handler
     */
    private function sendOnce(RequestInterface $request, Closure $handler): ResponseInterface
    {
        try {
            return $handler($request);
        } catch (ClientExceptionInterface $clientException) {
            // Guzzle throws on 4xx and 5xx when it streams; the response still carries the API error.
            if ($clientException instanceof BadResponseException) {
                return $clientException->getResponse();
            }

            throw match (true) {
                $this->isTimeout($clientException) => new TimeoutException($clientException),
                // Guzzle reports a cURL failure without a response (a reset connection, a TLS error) as a RequestException.
                $clientException instanceof NetworkExceptionInterface,
                $clientException instanceof RequestException && ! $clientException->hasResponse() => new ConnectionException($clientException),
                default => new TransporterException($clientException),
            };
        }
    }

    /**
     * An error the API marks with `x-should-retry` when it knows; otherwise 408, 409, 429 and 5xx are worth
     * another attempt. A success is never sent again, whatever its headers say.
     */
    private function shouldRetry(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        if ($status < 400) {
            return false;
        }

        return match (strtolower(trim($response->getHeaderLine('x-should-retry')))) {
            'true' => true,
            'false' => false,
            default => in_array($status, [408, 409, 429], true) || $status >= 500,
        };
    }

    /**
     * Whether a retry is left and the request can be sent again, rewinding its body for the next attempt:
     * a body that can't be rewound was already read.
     */
    private function canRetry(int $attempt, RequestInterface $request): bool
    {
        if ($attempt >= $this->maxRetries || ! $request->getBody()->isSeekable()) {
            return false;
        }

        $request->getBody()->rewind();

        return true;
    }

    /**
     * Waits as long as the response asks (`retry-after-ms`, then `retry-after`), otherwise
     * 0.5 seconds doubled per attempt up to 8, less up to a quarter at random.
     */
    private function wait(int $attempt, ResponseInterface|Throwable $cause): void
    {
        $milliseconds = $cause instanceof ResponseInterface ? $this->requestedDelay($cause) : null;

        if ($milliseconds === null) {
            $seconds = min(0.5 * 2 ** $attempt, 8.0);
            $milliseconds = (int) round($seconds * 1000 * (1 - mt_rand(0, 250) / 1000));
        }

        $this->logger?->warning('Retrying Anthropic request', [
            'attempt' => $attempt + 2,
            'delay_ms' => $milliseconds,
            'reason' => $cause instanceof ResponseInterface ? 'status '.$cause->getStatusCode() : $cause::class,
        ]);

        if ($this->sleep === null) {
            usleep($milliseconds * 1000);
        } else {
            ($this->sleep)($milliseconds);
        }
    }

    /**
     * The delay a response asks for, in milliseconds. Zero, a date in the past or a delay too long to sleep
     * leaves the backoff to decide, as in the official SDKs.
     */
    private function requestedDelay(ResponseInterface $response): ?int
    {
        $retryAfterMs = trim($response->getHeaderLine('retry-after-ms'));

        if (is_numeric($retryAfterMs)) {
            $milliseconds = (float) $retryAfterMs;
        } else {
            $seconds = ErrorException::retryAfterSeconds($response);
            $milliseconds = $seconds === null ? null : $seconds * 1000;
        }

        return $milliseconds !== null && $milliseconds > 0 && $milliseconds <= 2_147_483_647 ? (int) $milliseconds : null;
    }

    /**
     * The request's URL for a log line, without user info or a query string, where a gateway key may ride.
     */
    private function loggedUri(RequestInterface $request): string
    {
        return (string) $request->getUri()->withUserInfo('')->withQuery('');
    }

    /**
     * Whether the HTTP client gave up waiting: cURL says "timed out", Symfony's client "Idle timeout" or "Max duration".
     */
    private function isTimeout(ClientExceptionInterface $exception): bool
    {
        $message = $exception->getMessage();

        // The message ends with the request's URL, which may itself contain "timeout".
        if ($exception instanceof NetworkExceptionInterface || $exception instanceof RequestExceptionInterface) {
            $uri = $exception->getRequest()->getUri();
            $message = str_replace([(string) $uri, (string) $uri->withUserInfo('')], '', $message);
        }

        return preg_match('/timed? ?out|max duration/i', $message) === 1;
    }

    /**
     * Throws for every status of 400 and above: with the API's error when the body carries one,
     * otherwise with the first line of the body's text.
     */
    private function throwIfError(ResponseInterface $response, ?string $contents = null): void
    {
        if ($response->getStatusCode() < 400) {
            return;
        }

        $contents ??= (string) $response->getBody();

        try {
            /** @var mixed $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        $error = is_array($data) ? $data['error'] ?? null : null;

        /** @var array{message?: string|array<int, string>, type?: ?string, details?: mixed, request_id?: ?string}|null $error */
        $error = match (true) {
            is_string($error) => ['message' => $error],
            is_array($error) => $error,
            default => null,
        };

        // The body's request_id sits beside the error object, not inside it.
        if ($error !== null && is_array($data) && is_string($data['request_id'] ?? null)) {
            $error['request_id'] = $data['request_id'];
        }

        throw ErrorException::forStatus($error ?? $this->firstLine($contents) ?? $response->getReasonPhrase(), $response);
    }

    /**
     * The first line of text in a body that isn't an API error, such as a proxy's HTML error page.
     */
    private function firstLine(string $contents): ?string
    {
        // ASCII line endings only: \R would also match the 0x85 byte inside a UTF-8 character.
        foreach (preg_split('/\r\n|\r|\n/', strip_tags($contents)) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                return preg_match('/^.{1,200}/us', $line, $matches) === 1 ? $matches[0] : substr($line, 0, 200);
            }
        }

        return null;
    }
}
