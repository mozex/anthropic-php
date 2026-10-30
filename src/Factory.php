<?php

declare(strict_types=1);

namespace Anthropic;

use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Transporters\HttpTransporter;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\QueryParams;
use Closure;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Psr18Client;

class Factory
{
    /**
     * The API key for the requests.
     */
    private ?string $apiKey = null;

    /**
     * The bearer token for the requests, such as a Workload Identity Federation access token.
     */
    private ?string $authToken = null;

    /**
     * The API version for the requests.
     */
    private ?string $apiVersion = null;

    /**
     * The timeouts, in seconds, of the HTTP client the factory builds.
     */
    private int|float|null $timeout = null;

    private int|float|null $connectTimeout = null;

    private int $maxRetries = 0;

    /**
     * @var list<Closure(RequestInterface, Closure(RequestInterface): ResponseInterface): ResponseInterface>
     */
    private array $middleware = [];

    private ?LoggerInterface $logger = null;

    /**
     * @var (Closure(int): void)|null
     */
    private ?Closure $retrySleep = null;

    /**
     * The HTTP client for the requests.
     */
    private ?ClientInterface $httpClient = null;

    /**
     * The base URI for the requests.
     */
    private ?string $baseUri = null;

    /**
     * The HTTP headers for the requests.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * The query parameters for the requests.
     *
     * @var array<string, string|int>
     */
    private array $queryParams = [];

    private ?Closure $streamHandler = null;

    /**
     * Sets the API key for the requests.
     */
    public function withApiKey(string $apiKey): self
    {
        $this->apiKey = trim($apiKey);

        return $this;
    }

    /**
     * Sets a bearer token sent as `Authorization: Bearer <token>`, such as an access token from
     * Workload Identity Federation. With an API key set too, both headers are sent; a blank token sends none.
     */
    public function withAuthToken(string $authToken): self
    {
        $this->authToken = trim($authToken);

        return $this;
    }

    /**
     * Sets the workspace the requests run in, sent as the anthropic-workspace-id header.
     * Required with an API key that isn't scoped to one workspace.
     */
    public function withWorkspaceId(string $workspaceId): self
    {
        $workspaceId = trim($workspaceId);

        // The API rejects an empty header, so a blank value sends none, as the other setters do.
        if ($workspaceId === '') {
            return $this;
        }

        return $this->withHttpHeader('anthropic-workspace-id', $workspaceId);
    }

    /**
     * Sets the API version sent in the anthropic-version header.
     * If no version is provided the factory will use 2023-06-01.
     */
    public function withApiVersion(string $apiVersion): self
    {
        $this->apiVersion = trim($apiVersion);

        return $this;
    }

    /**
     * Sets the HTTP client for the requests.
     * If no client is provided the factory will try to find one using PSR-18 HTTP Client Discovery.
     */
    public function withHttpClient(ClientInterface $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Sets the stream handler for the requests. Not required when using Guzzle.
     */
    public function withStreamHandler(Closure $streamHandler): self
    {
        $this->streamHandler = $streamHandler;

        return $this;
    }

    /**
     * Sets the base URI for the requests, the full API root including any version path (api.anthropic.com/v1).
     * If no URI is provided the factory will use the default Anthropic API URI.
     */
    public function withBaseUri(string $baseUri): self
    {
        $this->baseUri = $baseUri;

        return $this;
    }

    /**
     * Sets the base URL the way the official SDKs read ANTHROPIC_BASE_URL: the URL without the API's /v1,
     * which is added unless the URL already ends in it. A blank value keeps the default.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($baseUrl === '') {
            return $this;
        }

        return $this->withBaseUri(str_ends_with($baseUrl, '/v1') ? $baseUrl : $baseUrl.'/v1');
    }

    /**
     * Sets the total timeout, in seconds, of the Guzzle client the factory builds.
     * A client passed to withHttpClient() keeps its own settings, so the factory throws then.
     */
    public function withTimeout(int|float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Sets the connection timeout, in seconds, of the Guzzle client the factory builds.
     * A client passed to withHttpClient() keeps its own settings, so the factory throws then.
     */
    public function withConnectTimeout(int|float $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    /**
     * Sets how many times a failed request is sent again: connection failures and timeouts, a RetryableException
     * from a middleware, and the statuses worth retrying (408, 409, 429, 5xx, or what `x-should-retry` says).
     * Defaults to 0; 2 is what the official SDKs use.
     */
    public function withMaxRetries(int $retries): self
    {
        if ($retries < 0) {
            throw new InvalidArgumentException('withMaxRetries() takes 0 or more.');
        }

        $this->maxRetries = $retries;

        return $this;
    }

    /**
     * Adds a middleware run around every HTTP attempt: `fn (RequestInterface $request, Closure $next): ResponseInterface`,
     * or an invokable object of that shape. The first one added runs outermost; throw a RetryableException to have
     * the request sent again.
     *
     * @param  callable(RequestInterface, Closure(RequestInterface): ResponseInterface): ResponseInterface  $middleware
     */
    public function withMiddleware(callable $middleware): self
    {
        $this->middleware[] = $middleware(...);

        return $this;
    }

    /**
     * Logs each attempt and its response (method, URL, status, request ID, duration, retries), never a body or a header.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Replaces the wait between retries, which gets the delay in milliseconds, so a test doesn't sleep.
     *
     * @param  Closure(int): void  $sleep
     */
    public function withRetrySleep(Closure $sleep): self
    {
        $this->retrySleep = $sleep;

        return $this;
    }

    /**
     * Adds a custom HTTP header to the requests.
     */
    public function withHttpHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Adds a custom query parameter to the request url.
     */
    public function withQueryParam(string $name, string $value): self
    {
        $this->queryParams[$name] = $value;

        return $this;
    }

    /**
     * Creates a new Anthropic Client.
     */
    public function make(): Client
    {
        if ($this->httpClient !== null && ($this->timeout !== null || $this->connectTimeout !== null)) {
            throw new InvalidArgumentException('withTimeout() and withConnectTimeout() apply to the HTTP client the factory builds: set the timeouts on the client you passed to withHttpClient() instead.');
        }

        $headers = Headers::create();

        if ($this->apiKey !== null) {
            $headers = Headers::withAuthorization(ApiKey::from($this->apiKey));
        }

        if ($this->authToken !== null && $this->authToken !== '') {
            $headers = $headers->withCustomHeader('Authorization', 'Bearer '.$this->authToken);
        }

        $headers = $headers->withCustomHeader('anthropic-version', $this->apiVersion ?: '2023-06-01');

        foreach ($this->headers as $name => $value) {
            $headers = $headers->withCustomHeader($name, $value);
        }

        $baseUri = BaseUri::from($this->baseUri ?: 'api.anthropic.com/v1');

        $queryParams = QueryParams::create();
        foreach ($this->queryParams as $name => $value) {
            $queryParams = $queryParams->withParam($name, $value);
        }

        // Only a client from withHttpClient() is kept: a built one would make the next make() refuse the timeouts.
        $client = $this->httpClient ?? $this->makeHttpClient();

        $sendAsync = $this->makeStreamHandler($client);

        $transporter = new HttpTransporter($client, $baseUri, $headers, $queryParams, $sendAsync, $this->maxRetries, $this->middleware, $this->logger, $this->retrySleep);

        return new Client($transporter);
    }

    /**
     * Builds a Guzzle client when a timeout is set, otherwise finds one through PSR-18 HTTP Client Discovery.
     */
    private function makeHttpClient(): ClientInterface
    {
        if ($this->timeout === null && $this->connectTimeout === null) {
            return Psr18ClientDiscovery::find();
        }

        if (! class_exists(GuzzleClient::class)) {
            throw new InvalidArgumentException('withTimeout() and withConnectTimeout() configure a Guzzle client, which isn\'t installed: run `composer require guzzlehttp/guzzle`, or pass a client with its own timeouts to withHttpClient().');
        }

        return new GuzzleClient(array_filter([
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
        ], fn (int|float|null $seconds): bool => $seconds !== null));
    }

    /**
     * Creates a new stream handler for "stream" requests.
     */
    private function makeStreamHandler(ClientInterface $client): Closure
    {
        if (! is_null($this->streamHandler)) {
            return $this->streamHandler;
        }

        if ($client instanceof GuzzleClient) {
            return fn (RequestInterface $request): ResponseInterface => $client->send($request, ['stream' => true]);
        }

        if ($client instanceof Psr18Client) { // @phpstan-ignore-line
            return fn (RequestInterface $request): ResponseInterface => $client->sendRequest($request); // @phpstan-ignore-line
        }

        return function (RequestInterface $_): never {
            throw new Exception('To use stream requests you must provide an stream handler closure via the Anthropic factory.');
        };
    }
}
