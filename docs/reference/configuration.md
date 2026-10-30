---
title: Configuration
weight: 1
---

The simplest way to create a client with this SDK is through `Anthropic::client()`:

```php
$client = Anthropic::client('your-api-key');
```

This uses auto-discovered defaults: the official Anthropic API endpoint, a PSR-18 HTTP client found via `php-http/discovery`, and standard headers. For most projects, this is enough.

To configure the client from the environment variables the official SDKs read, use `Anthropic::fromEnvironment()`:

```php
$client = Anthropic::fromEnvironment();
```

It reads `ANTHROPIC_API_KEY`, or `ANTHROPIC_AUTH_TOKEN` when no key is set (one credential, as the official SDKs use), `ANTHROPIC_BASE_URL` with the official SDKs' meaning (see [Base URI](#base-uri)), and `ANTHROPIC_CUSTOM_HEADERS`, one `Name: value` header per line. It throws an `InvalidArgumentException` when neither credential is set. `Anthropic::client($apiKey)` stays as it was.

When you need to customize anything, use the factory.

## Factory options

The factory lets you configure every aspect of the client:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withWorkspaceId('wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ')
    ->withApiVersion('2026-10-21')
    ->withBaseUri('anthropic.example.com/v1')
    ->withHttpClient($httpClient)
    ->withHttpHeader('X-Custom-Header', 'value')
    ->withQueryParam('my-param', 'bar')
    ->withStreamHandler($streamHandler)
    ->make();
```

Call `make()` at the end to create the client. Each `with*` method returns the factory, so you can chain them.

### API key

```php
->withApiKey('your-api-key')
```

The client sends this as a `x-api-key` header on every request. Leading and trailing whitespace is trimmed automatically.

### Auth token

```php
->withAuthToken('your-access-token')
```

Sends `Authorization: Bearer <token>` instead, for a short-lived access token from [Workload Identity Federation](https://platform.claude.com/docs/en/manage-claude/workload-identity-federation) (`POST /v1/oauth/token`) or an API key you'd rather send that way. Set it in place of the API key; with both set, both headers go out.

### Workspace

```php
->withWorkspaceId('wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ')
```

Sends the `anthropic-workspace-id` header, which selects the workspace a request runs in. An API key that isn't scoped to one workspace needs it; a key created for a single workspace runs there without it, and Workload Identity Federation tokens pick the workspace when you exchange them.

### API version

```php
->withApiVersion('2026-10-21')
```

Sets the `anthropic-version` header sent on every request. It defaults to `2023-06-01`, and a blank value keeps that default.

Version `2026-10-21` rejects some requests the older version accepts. Setting `temperature`, `top_p` or `top_k` returns a 400 on every model, and so do `document` blocks placed directly in an `assistant` message and non-empty `citations` on `text` blocks in a `user` message. Read the [migration guide](https://platform.claude.com/docs/en/api/migration-2026-10-21) before you switch. Message Batches keep the version of the request that created them, so their results come back under that version.

A `withHttpHeader('anthropic-version', ...)` call still wins over this option, since custom headers are applied last.

### Base URI

```php
->withBaseUri('anthropic.example.com/v1')
```

Defaults to `api.anthropic.com/v1`. Override this when you're routing through a proxy, a gateway, or a self-hosted endpoint. Pass the full API root, `/v1` included: the client adds only the endpoint path (`/messages`, `/models`) after it, and drops a trailing slash.

The official Anthropic SDKs read a base URL from `ANTHROPIC_BASE_URL` with a different meaning: the URL without `/v1`, which they add. For a value meant for them, such as a gateway's documented `https://gateway.example.com/anthropic`, use `withBaseUrl()`, which adds `/v1` unless the URL already ends in it:

```php
->withBaseUrl('https://gateway.example.com/anthropic') // requests go to .../anthropic/v1/messages
```

A blank value keeps the default endpoint.

### HTTP client

```php
->withHttpClient(new \GuzzleHttp\Client(['timeout' => 120]))
```

Provide your own PSR-18 HTTP client. If you don't set one, the factory uses `php-http/discovery` to find a compatible client in your project automatically.

### Custom headers

```php
->withHttpHeader('X-Custom-Header', 'value')
```

Adds a header to every request. Call it multiple times for multiple headers. The client always sends the `anthropic-version` header (see [API version](#api-version)) and the authorization header; your custom headers are added on top, and one with the same name replaces the default.

### Query parameters

```php
->withQueryParam('my-param', 'bar')
```

Adds a query parameter to every request URL. Useful for routing or analytics parameters required by proxy setups.

### Stream handler

```php
->withStreamHandler(fn (RequestInterface $request): ResponseInterface =>
    $httpClient->send($request, ['stream' => true])
)
```

Custom handler for streaming requests. The handler receives a PSR-7 `RequestInterface` and must return a PSR-7 `ResponseInterface` with a streaming body.

You only need this for HTTP clients other than Guzzle or Symfony. Both of those are detected automatically and handled without any extra configuration.

## Beta features

Anthropic's beta features are opt-in via the `anthropic-beta` header. This SDK lets you enable them per request by passing a `betas` array alongside your normal parameters:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
    'betas' => [
        'interleaved-thinking-2025-05-14',
        'extended-cache-ttl-2025-04-11',
    ],
]);
```

The SDK pulls `betas` out of the parameters before serialization, so nothing leaks into the JSON body or query string. It becomes a comma-separated `anthropic-beta` header on that one request only.

If some beta is enabled for every request in your app, set it globally on the factory and skip the per-call array:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withHttpHeader('anthropic-beta', 'interleaved-thinking-2025-05-14')
    ->make();
```

Global and per-request combine. If you set `anthropic-beta: interleaved-thinking-2025-05-14` globally and pass `betas: ['files-api-2025-04-14']` on one call, that call sends both, de-duplicated.

Some resources require a specific beta header to work at all. The SDK auto-injects those for you, so you never have to type the version string for the resource you're already using. You only pass `betas` when you want to add additional ones on top.

For how beta headers work, multi-beta syntax, and version naming conventions, see the [Beta headers reference](https://platform.claude.com/docs/en/api/beta-headers) on the Anthropic docs. Individual beta feature names are documented on the page for the feature they gate (Files API, extended cache TTL, interleaved thinking, and so on).

## HTTP client setup

### Guzzle

Guzzle is the most common choice. Everything works out of the box, including streaming:

```bash
composer require guzzlehttp/guzzle
```

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withHttpClient(new \GuzzleHttp\Client([
        'timeout' => 120,
        'connect_timeout' => 5,
    ]))
    ->make();
```

No stream handler needed. The factory detects Guzzle and configures streaming automatically.

### Symfony HTTP Client

Symfony's HTTP client also works without a custom stream handler:

```bash
composer require symfony/http-client
```

```php
use Symfony\Component\HttpClient\Psr18Client;

$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withHttpClient(new Psr18Client())
    ->make();
```

### Other PSR-18 clients

For any other PSR-18 client, you'll need to provide a stream handler that tells the client how to return a streaming response:

```php
$httpClient = new SomeOtherHttpClient();

$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withHttpClient($httpClient)
    ->withStreamHandler(function (RequestInterface $request) use ($httpClient): ResponseInterface {
        // Your client's way of returning a streaming response
        return $httpClient->sendRequest($request);
    })
    ->make();
```

Without a stream handler, calling `createStreamed()` will throw an exception.

## Timeout configuration

The default timeout depends on your HTTP client. For long-running requests (large context windows, [extended thinking](../usage/thinking.md), [code execution](../usage/server-tools.md)), you'll likely want to increase it.

When the factory builds the HTTP client, set the timeouts on the factory:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withTimeout(300)        // seconds for a request; on a stream, Guzzle applies it to each read
    ->withConnectTimeout(5)   // seconds to connect
    ->make();
```

These build a Guzzle client, so they need `guzzlehttp/guzzle` installed. With a client of your own passed to `withHttpClient()`, `make()` throws an `InvalidArgumentException`: set the timeouts on that client instead, as below. A timeout that runs out throws a `TimeoutException` (see [Error Handling](./error-handling.md#http-transport-errors)).

On a client you pass in, with Guzzle:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 300]))
    ->make();
```

With Symfony:

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$httpClient = HttpClient::create(['timeout' => 300]);
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withHttpClient(new Psr18Client($httpClient))
    ->make();
```

## Retries

The client doesn't retry by default. Turn it on with the number of extra attempts; two is what the official SDKs default to:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withMaxRetries(2)
    ->make();
```

A request is sent again after a connection failure or a timeout, or a response the API marks as worth retrying: 408, 409, 429 and every 5xx, including the 529 overload, unless the response's `x-should-retry` header says otherwise. Between attempts the client waits what the response asks for (`retry-after-ms`, then `retry-after`), or, when it asks for nothing or for no wait at all, backs off: half a second, doubled each attempt up to 8 seconds, less up to a quarter at random. When the retries run out, the last error is thrown as usual (see [Error Handling](./error-handling.md)).

A stream is retried only before it starts: once events arrive, an error in the middle of the stream reaches your loop.

Two things to plan for. A `retry-after` of several minutes holds the call that long, so where a request can't wait, turn retries off for it with `withOptions(['max_retries' => 0])`. And a timeout or a dropped connection can hide a request the API already received: the retry sends it again, so a message or a batch can be created twice. In tests, `withRetrySleep(fn (int $milliseconds) => ...)` replaces the wait, so a test of your retry handling doesn't sleep.

## Middleware

A middleware runs around every HTTP attempt, retries included, and gets the PSR-7 request and the next handler:

```php
use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withMiddleware(function (RequestInterface $request, Closure $next): ResponseInterface {
        $response = $next($request->withHeader('X-Request-Source', 'billing-report'));

        // inspect $response, record metrics ...

        return $response;
    })
    ->make();
```

An invokable object with the same signature works too. The first middleware added runs outermost. Every status comes back as a response here, errors included; the client throws after the middleware has seen it. To have the request sent again, throw `Anthropic\Exceptions\RetryableException`: it counts against `withMaxRetries()`, and once the retries run out it reaches the caller.

## Logging

`withLogger()` takes any PSR-3 logger (`psr/log`):

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withLogger($logger)
    ->make();
```

Each attempt logs an `info` line with the method, the URL and the attempt number, its response another with the status, the request ID and the duration, and each retry a `warning` with the delay and the reason. Bodies and headers are never logged, so keys and prompts stay out of your logs.

## OpenTelemetry

`OpenTelemetryMiddleware` traces every attempt with OpenTelemetry. It needs `open-telemetry/api`, which the package only suggests, and an SDK your app sets up:

```bash
composer require open-telemetry/api
```

```php
use Anthropic\Middleware\OpenTelemetryMiddleware;

$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withMiddleware(new OpenTelemetryMiddleware)
    ->make();
```

Each attempt, a retry included, gets a client span: `anthropic.messages.create` and `anthropic.messages.count_tokens` for those calls, the HTTP method for any other. The spans carry the attributes the official SDKs set, from the GenAI semantic conventions: `gen_ai.request.model`, `gen_ai.request.max_tokens`, `gen_ai.response.id`, `gen_ai.usage.input_tokens`, `gen_ai.usage.output_tokens` and the cache counts, the stop reason, the request ID, and `error.type` on a failure (the API's error type, else the status; `connection_error` or `client_timeout_error` when no answer came). Prompts, answers and keys are never recorded, and a failure to record never fails the request. A streamed message's span ends when its headers arrive, so it has the request's attributes but no usage, and an error in the middle of the stream doesn't reach it.

The span is the current one while the request goes out, so instrumentation below it (an HTTP client's, a log processor's) nests under it, and the registered propagator writes the trace context into the request's headers: `traceparent` with the W3C propagator, which an SDK set up with `OTEL_PROPAGATORS=tracecontext,baggage` (autoload's default) registers. The middleware reads the registered tracer provider and propagator on each request, so a client built before your SDK starts still records; pass your own as `new OpenTelemetryMiddleware($tracer, $propagator)`, or `OpenTelemetry\Context\Propagation\NoopTextMapPropagator::getInstance()` as the propagator to send no trace context.

Add it after `RefusalFallbackMiddleware` and each fallback attempt gets a span of its own; before it, one span covers the whole chain.

## Building from a config array

`withConfig()` takes the settings a framework's config file holds, in one array, and applies the same rules the Laravel package does:

```php
$client = Anthropic::factory()
    ->withConfig([
        'api_key' => getenv('ANTHROPIC_API_KEY'),
        'workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy',
        'betas' => ['compact-2026-09-04'],
        'max_retries' => 2,
        'headers' => ['X-Request-Source' => 'billing-report'],
    ])
    ->make();
```

The keys are `api_key`, `auth_token` (used only when there's no key), `workspace_id`, `api_version`, `base_url` (as `ANTHROPIC_BASE_URL`, without `/v1`) or `base_uri` (the full API root), `betas` (a list, or a comma-separated string as an environment variable gives it), `timeout`, `connect_timeout`, `max_retries`, `middleware` (one callable or a list), `http_client` and `headers` (applied last).

A null, false or blank value keeps the default, so `getenv()` of an unset variable does too, and so does a timeout of 0. The credential in the array replaces one set before it, so the client never sends both. An unknown key, a value of the wrong type (a beta that isn't a string, a `max_retries` of `2.5`), `base_url` beside `base_uri` and a timeout beside `http_client` throw before anything is applied, so a misspelt setting can't go unnoticed.

## Per-request options

`withOptions()` returns a client whose requests carry extra settings, and leaves the client you call it on unchanged. Use it for a request that runs in another workspace, needs a beta the rest of your app doesn't, or carries a tracing header:

```php
$tenant = $client->withOptions([
    'workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ',
    'betas' => ['server-side-fallback-2026-07-01'],
    'headers' => ['X-Request-Source' => 'billing-report'],
]);

$tenant->models()->retrieve('claude-sonnet-5-5'); // carries all three
$client->models()->retrieve('claude-sonnet-5-5'); // carries none of them
```

| Option | What it sets |
|--------|--------------|
| `headers` | Extra headers, as name => value pairs |
| `query` | Extra query parameters |
| `betas` | Betas added to the `anthropic-beta` header, with the factory's and any `betas` passed on a call |
| `api_version` | The `anthropic-version` header, in place of the factory's |
| `workspace_id` | The `anthropic-workspace-id` header |
| `user_profile_id` | The `anthropic-user-profile-id` header, for requests made on an end user's behalf |
| `max_retries` | The number of retries, in place of the factory's `withMaxRetries()` |

This is also how methods that take only an ID, such as `models()->retrieve()` or `batches()->results()`, get a beta header. Calling `withOptions()` on a client that already has options merges them: headers and query parameters by name, betas added up, the rest replaced. An unknown option throws an `InvalidArgumentException`. A timeout per request isn't possible through PSR-18; build a separate client with its own HTTP client for that.

## Reading the settings back

`settings()` on a client returns what it sends with every request, so a diagnostic page or command can show what's actually in use:

```php
$settings = $client->settings();

$settings->baseUri;        // 'https://api.anthropic.com/v1/'
$settings->apiVersion;     // '2023-06-01'
$settings->betas;          // ['compact-2026-09-04']
$settings->workspaceId;    // null
$settings->maxRetries;     // 2
$settings->timeout;        // 120.0
$settings->connectTimeout; // null
```

It never holds the key or the token. The timeouts are known only for the Guzzle client the factory builds (`withTimeout()`, `withConnectTimeout()`); with your own HTTP client they're `null`. A `withOptions()` client reports its own betas, version and retries. `toArray()` gives them all as one array.

## PSR-18 client discovery

When you don't provide an HTTP client, the factory uses `php-http/discovery` to find one. This works if your project has any PSR-18 compatible client installed (Guzzle, Symfony HTTP Client, Buzz, etc.).

If discovery fails, you'll get an error at runtime. Either install a client explicitly:

```bash
composer require guzzlehttp/guzzle
```

Or allow the `php-http/discovery` Composer plugin to install one automatically:

```bash
composer config allow-plugins.php-http/discovery true
```
