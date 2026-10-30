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

| Option | Sends |
|--------|-------|
| `headers` | Extra headers, as name => value pairs |
| `query` | Extra query parameters |
| `betas` | Betas added to the `anthropic-beta` header, with the factory's and any `betas` passed on a call |
| `api_version` | The `anthropic-version` header, in place of the factory's |
| `workspace_id` | The `anthropic-workspace-id` header |
| `user_profile_id` | The `anthropic-user-profile-id` header, for requests made on an end user's behalf |

This is also how methods that take only an ID, such as `models()->retrieve()` or `batches()->results()`, get a beta header. Calling `withOptions()` on a client that already has options merges them: headers and query parameters by name, betas added up, the rest replaced. An unknown option throws an `InvalidArgumentException`. A timeout per request isn't possible through PSR-18; build a separate client with its own HTTP client for that.

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
