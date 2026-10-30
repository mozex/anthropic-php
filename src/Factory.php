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
     * The keys withConfig() takes.
     */
    private const CONFIG_KEYS = ['api_key', 'auth_token', 'workspace_id', 'api_version', 'base_url', 'base_uri', 'betas', 'timeout', 'connect_timeout', 'max_retries', 'middleware', 'http_client', 'headers'];

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
     * Applies a configuration array, as a framework's config file holds one: `api_key`, `auth_token` (used only
     * without a key: one credential, as in the official SDKs), `workspace_id`, `api_version`, `base_url` (the official
     * SDKs' `ANTHROPIC_BASE_URL`, without `/v1`) or `base_uri` (the full API root), `betas` (a list or a comma-separated
     * string, sent as `anthropic-beta`), `timeout` and `connect_timeout` (seconds), `max_retries`, `middleware` (one
     * callable or a list), `http_client` and `headers` (applied last). A null, false or blank value keeps the default
     * (so `getenv()` of an unset variable does), and so does a timeout of 0. A credential replaces the one set before;
     * an unknown key, a value of the wrong type, both base keys together and a timeout beside an `http_client` throw,
     * before anything is applied.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): self
    {
        $unknown = array_diff(array_keys($config), self::CONFIG_KEYS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown config key ['.implode('], [', $unknown).']; the keys are '.implode(', ', self::CONFIG_KEYS).'.');
        }

        // getenv() gives false for an unset variable, and env() an empty string for an empty line.
        $config = array_map(fn (mixed $value): mixed => $value === false || (is_string($value) && trim($value) === '') ? null : $value, $config);

        $apiKey = self::configString($config['api_key'] ?? null, 'api_key');
        $authToken = self::configString($config['auth_token'] ?? null, 'auth_token');
        $workspaceId = self::configString($config['workspace_id'] ?? null, 'workspace_id');
        $apiVersion = self::configString($config['api_version'] ?? null, 'api_version');
        $baseUri = self::configString($config['base_uri'] ?? null, 'base_uri');
        $baseUrl = self::configString($config['base_url'] ?? null, 'base_url');

        if ($baseUri !== null && $baseUrl !== null) {
            throw new InvalidArgumentException('Set base_uri (the full API root) or base_url (without /v1), not both.');
        }

        $betas = self::configBetas($config['betas'] ?? null);
        $timeout = self::configSeconds($config['timeout'] ?? null, 'timeout');
        $connectTimeout = self::configSeconds($config['connect_timeout'] ?? null, 'connect_timeout');
        $maxRetries = self::configRetries($config['max_retries'] ?? null);
        $middleware = self::configMiddleware($config['middleware'] ?? null);
        $headers = self::configHeaders($config['headers'] ?? null);
        $httpClient = $config['http_client'] ?? null;

        if ($httpClient !== null && ! $httpClient instanceof ClientInterface) {
            throw new InvalidArgumentException('The [http_client] config value must be a PSR-18 client.');
        }

        if ($httpClient !== null && ($timeout !== null || $connectTimeout !== null)) {
            throw new InvalidArgumentException('The [timeout] and [connect_timeout] config values apply to the HTTP client the factory builds: set them on your [http_client] instead.');
        }

        // One credential: the key when one is set, the token otherwise, either replacing what an earlier call set.
        if ($apiKey !== null) {
            $this->withApiKey($apiKey);
            $this->authToken = null;
        } elseif ($authToken !== null) {
            $this->withAuthToken($authToken);
            $this->apiKey = null;
        }

        if ($workspaceId !== null) {
            $this->withWorkspaceId($workspaceId);
        }

        if ($apiVersion !== null) {
            $this->withApiVersion($apiVersion);
        }

        if ($baseUri !== null) {
            $this->withBaseUri($baseUri);
        }

        if ($baseUrl !== null) {
            $this->withBaseUrl($baseUrl);
        }

        if ($betas !== []) {
            $this->withHttpHeader('anthropic-beta', implode(',', $betas));
        }

        if ($timeout !== null) {
            $this->withTimeout($timeout);
        }

        if ($connectTimeout !== null) {
            $this->withConnectTimeout($connectTimeout);
        }

        if ($maxRetries !== null) {
            $this->withMaxRetries($maxRetries);
        }

        foreach ($middleware as $entry) {
            $this->withMiddleware($entry);
        }

        if ($httpClient instanceof ClientInterface) {
            $this->withHttpClient($httpClient);
        }

        foreach ($headers as $name => $value) {
            $this->withHttpHeader($name, $value);
        }

        return $this;
    }

    /**
     * A string setting, trimmed, or null for none.
     */
    private static function configString(mixed $value, string $key): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The [{$key}] config value must be a string.");
        }

        return trim($value);
    }

    /**
     * The config's betas, from a list or a comma-separated string: trimmed, without blanks or repeats.
     *
     * @return list<string>
     */
    private static function configBetas(mixed $betas): array
    {
        $betas = is_string($betas) ? explode(',', $betas) : ($betas ?? []);

        if (! is_array($betas)) {
            throw new InvalidArgumentException('The [betas] config value must be a list of beta names or a comma-separated string.');
        }

        $names = [];

        foreach ($betas as $beta) {
            if (! is_string($beta)) {
                throw new InvalidArgumentException('Each beta in the [betas] config value must be a string.');
            }

            if (trim($beta) !== '') {
                $names[] = trim($beta);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * A timeout in seconds; null for none or 0, which keep the HTTP client's default.
     */
    private static function configSeconds(mixed $seconds, string $key): ?float
    {
        if ($seconds === null) {
            return null;
        }

        if (! is_numeric($seconds) || (float) $seconds < 0) {
            throw new InvalidArgumentException("The [{$key}] config value must be a number of seconds.");
        }

        return (float) $seconds > 0 ? (float) $seconds : null;
    }

    /**
     * The retry count: a whole number of 0 or more, or null for none.
     */
    private static function configRetries(mixed $retries): ?int
    {
        if ($retries === null) {
            return null;
        }

        if (is_int($retries) && $retries >= 0) {
            return $retries;
        }

        if (is_string($retries) && ctype_digit(trim($retries))) {
            return (int) trim($retries);
        }

        throw new InvalidArgumentException('The [max_retries] config value must be a whole number of 0 or more.');
    }

    /**
     * The config's middleware: one callable or a list of them.
     *
     * @return list<callable>
     */
    private static function configMiddleware(mixed $middleware): array
    {
        if ($middleware === null) {
            return [];
        }

        // An array is a list, never an [object, method] callable.
        if (! is_array($middleware) && is_callable($middleware)) {
            return [$middleware];
        }

        if (! is_array($middleware)) {
            throw new InvalidArgumentException('The [middleware] config value must be a callable or a list of them.');
        }

        $entries = [];

        foreach ($middleware as $entry) {
            if (! is_callable($entry)) {
                throw new InvalidArgumentException('Each middleware must be callable: fn (RequestInterface $request, Closure $next): ResponseInterface. An array is read as a list, so pass a method as $object->handle(...).');
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * The config's headers by name, without the null or blank ones; a boolean (env() gives one for `true`) is sent as
     * `true` or `false`.
     *
     * @return array<string, string>
     */
    private static function configHeaders(mixed $headers): array
    {
        if ($headers === null) {
            return [];
        }

        if (! is_array($headers)) {
            throw new InvalidArgumentException('The [headers] config value must be an array of header values by name.');
        }

        $values = [];

        foreach ($headers as $name => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (! is_string($name) || ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value))) {
                throw new InvalidArgumentException('Each header in the [headers] config value must be a string, int, float or bool under its name.');
            }

            if ($value !== null && trim((string) $value) !== '') {
                $values[$name] = (string) $value;
            }
        }

        return $values;
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

        // Timeouts exist only for the Guzzle client built here (make() refuses them beside yours), so only theirs are known.
        $transporter = new HttpTransporter($client, $baseUri, $headers, $queryParams, $sendAsync, $this->maxRetries, $this->middleware, $this->logger, $this->retrySleep, $this->timeout, $this->connectTimeout);

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
