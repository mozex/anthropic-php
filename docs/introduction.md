---
title: Introduction
weight: 0
---

Anthropic PHP is a community-maintained PHP SDK for the [Anthropic API](https://platform.claude.com/docs/en/about-claude/models/overview). It gives you typed access to Claude's Messages API, streaming, tool use, extended thinking, web search, code execution, message batches, and more.

> **Using Laravel?** Check out [Anthropic Laravel](https://github.com/mozex/anthropic-laravel), which wraps this SDK with service container integration, config-based setup, and an `Anthropic` facade.

## Why this package

**Built-in test client.** The `ClientFake` class lets you queue fake responses, run your code, and assert exactly which API requests were sent. No HTTP mocking libraries, no test servers. Drop it into your test suite and go. [See the testing docs →](./reference/testing.md)

**Forward-compatible.** The SDK follows a pass-through design: your parameters go directly to the API as-is, and responses come back as typed, immutable PHP objects. When Anthropic adds new API parameters, they work immediately without waiting for a client update. You don't get blocked by SDK releases.

**Typed, immutable responses.** Every response is a `readonly` PHP object with typed properties. Access `$response->usage->inputTokens` instead of digging through nested arrays. Full IDE autocompletion, zero guesswork.

**Rate limits on every response.** Call `$response->meta()` on any response (including streams and batch results) to get your current request limits, token limits, and reset times. No extra API calls needed.

**Any HTTP client.** Built on PSR-18. Works with Guzzle, Symfony HTTP Client, Buzz, or whatever your project already uses. No vendor lock-in, no framework coupling.

## Installation

> **Requires [PHP 8.2+](https://www.php.net/releases/)** - see [all version requirements](https://mozex.dev/docs/anthropic-php/v1/requirements)

Install via Composer:

```bash
composer require mozex/anthropic-php
```

The included `php-http/discovery` plugin finds and installs a compatible PSR-18 HTTP client automatically. If you want to use a specific one (like Guzzle or Symfony), see [Configuration](./reference/configuration.md).

## Quick start

Create a client with your API key and send a message:

```php
$client = Anthropic::client('your-api-key');

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

echo $response->content[0]->text; // Hello! How can I assist you today?
```

That's the simplest case. The `create` method returns a `CreateResponse` object with typed properties for the message ID, model, content blocks, token usage, and stop reason.

```php
$response->id;          // 'msg_01BSy0WCV7QR2adFBauynAX7'
$response->model;       // 'claude-sonnet-4-6'
$response->stop_reason; // 'end_turn'
$response->usage->inputTokens;  // 10
$response->usage->outputTokens; // 19
```

## Client configuration

For most use cases, `Anthropic::client()` is all you need. When you need more control, use the factory:

```php
$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withBaseUri('anthropic.example.com/v1')
    ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 120]))
    ->withHttpHeader('X-Custom-Header', 'value')
    ->withQueryParam('my-param', 'bar')
    ->withStreamHandler(fn ($request) => $httpClient->send($request, [
        'stream' => true,
    ]))
    ->make();
```

| Method | Purpose |
|--------|---------|
| `withApiKey()` | Your Anthropic API key. Required. |
| `withBaseUri()` | Override the API endpoint. Defaults to `api.anthropic.com/v1`. Useful for proxies or self-hosted setups. |
| `withHttpClient()` | Provide your own PSR-18 HTTP client instead of auto-discovery. |
| `withHttpHeader()` | Add custom HTTP headers to every request. |
| `withQueryParam()` | Add query parameters to every request URL. |
| `withStreamHandler()` | Custom handler for streaming requests. Only needed for HTTP clients other than Guzzle or Symfony. |

See the [Configuration](./reference/configuration.md) page for detailed examples, including timeout configuration and non-Guzzle HTTP clients.

## What's covered

This documentation covers every feature the client supports:

**Usage** covers all API operations:

- [Messages](./usage/messages.md): Send messages and read responses
- [Streaming](./usage/streaming.md): Stream responses token by token
- [Tool Use](./usage/tool-use.md): Give Claude custom functions to call
- [Thinking](./usage/thinking.md): Adaptive and extended thinking for complex reasoning
- [Server Tools](./usage/server-tools.md): Web search and sandboxed code execution
- [Citations](./usage/citations.md): Source citations from documents and web search
- [Token Counting](./usage/token-counting.md): Count tokens before sending
- [Models](./usage/models.md): List and inspect available models
- [Batches](./usage/batches.md): Process large volumes of requests asynchronously
- [Completions](./usage/completions.md): Legacy Text Completions API

**Reference** covers cross-cutting concerns:

- [Configuration](./reference/configuration.md): Factory options, HTTP clients, stream handlers
- [Error Handling](./reference/error-handling.md): Exception types and how to catch them
- [Meta Information](./reference/meta-information.md): Rate limits, request IDs, token limits
- [Testing](./reference/testing.md): Fake client, mock responses, and assertions
