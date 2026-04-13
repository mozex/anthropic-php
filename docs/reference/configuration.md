---
title: Configuration
weight: 1
---

The simplest way to create a client with this SDK is through `Anthropic::client()`:

```php
$client = Anthropic::client('your-api-key');
```

This uses auto-discovered defaults: the official Anthropic API endpoint, a PSR-18 HTTP client found via `php-http/discovery`, and standard headers. For most projects, this is enough.

When you need to customize anything, use the factory.

## Factory options

The factory lets you configure every aspect of the client:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
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

Required. The client sends this as a `x-api-key` header on every request. Leading and trailing whitespace is trimmed automatically.

### Base URI

```php
->withBaseUri('anthropic.example.com/v1')
```

Defaults to `api.anthropic.com/v1`. Override this when you're routing through a proxy, a gateway, or a self-hosted endpoint.

### HTTP client

```php
->withHttpClient(new \GuzzleHttp\Client(['timeout' => 120]))
```

Provide your own PSR-18 HTTP client. If you don't set one, the factory uses `php-http/discovery` to find a compatible client in your project automatically.

### Custom headers

```php
->withHttpHeader('X-Custom-Header', 'value')
```

Adds a header to every request. Call it multiple times for multiple headers. The client always sends `anthropic-version: 2023-06-01` and the authorization header; your custom headers are added on top.

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

With Guzzle:

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
