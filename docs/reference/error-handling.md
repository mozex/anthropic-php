---
title: Error Handling
weight: 2
---

The SDK throws typed exceptions when something goes wrong. All API errors, HTTP failures, and response parsing issues are covered.

## API errors

When the Anthropic API returns an error response, the client throws `Anthropic\Exceptions\ErrorException`:

```php
use Anthropic\Exceptions\ErrorException;

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 1024,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);
} catch (ErrorException $e) {
    $e->getMessage();    // 'Overloaded'
    $e->getErrorType();  // 'overloaded_error'
    $e->getStatusCode(); // 529
}
```

`ErrorException` provides three methods:

| Method | Returns | Description |
|--------|---------|-------------|
| `getMessage()` | `string` | The error message from the API |
| `getErrorType()` | `?string` | The error type (e.g., `invalid_request_error`, `authentication_error`, `overloaded_error`) |
| `getStatusCode()` | `int` | The HTTP status code |

The raw PSR-7 response is also available on the `response` property if you need to inspect headers or the full body:

```php
$e->response; // Psr\Http\Message\ResponseInterface or null
```

> **Note:** For streamed requests, the status code might be `200` even when an error occurs, because the HTTP response started successfully before the error happened mid-stream.

### Common API errors

| Status | Error type | Typical cause |
|--------|-----------|---------------|
| 400 | `invalid_request_error` | Bad parameters, missing required fields |
| 401 | `authentication_error` | Invalid or missing API key |
| 402 | `billing_error` | Issue with billing or payment information |
| 403 | `permission_error` | API key doesn't have access to the requested resource |
| 404 | `not_found_error` | Invalid model name or resource ID |
| 413 | `request_too_large` | Request exceeds the maximum allowed size (32 MB for Messages, 256 MB for Batches) |
| 429 | `rate_limit_error` | Too many requests (see below) |
| 500 | `api_error` | Server-side issue |
| 504 | `timeout_error` | Request timed out while processing; consider using [streaming](../usage/streaming.md) |
| 529 | `overloaded_error` | API is temporarily overloaded |

## Rate limit errors

HTTP 429 responses throw `Anthropic\Exceptions\RateLimitException`, a subclass of `ErrorException`. Since it extends `ErrorException`, existing `catch (ErrorException $e)` blocks will catch it too. If you want to handle rate limits specifically, catch it first:

```php
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\ErrorException;

try {
    $response = $client->messages()->create([...]);
} catch (RateLimitException $e) {
    // Rate limited: check when you can retry
    $retryAfter = $e->response->getHeaderLine('Retry-After');

    // Wait and retry
    sleep((int) $retryAfter);
} catch (ErrorException $e) {
    // All other API errors
}
```

The `Retry-After` header tells you how many seconds to wait before retrying. It's available on the PSR-7 `response` object.

For more on rate limits, see [Meta Information](./meta-information.md), which covers how to read your current limits and remaining quota from response headers.

## HTTP transport errors

When the HTTP request itself fails (network error, DNS resolution failure, connection timeout, etc.), the client throws `Anthropic\Exceptions\TransporterException`:

```php
use Anthropic\Exceptions\TransporterException;

try {
    $response = $client->messages()->create([...]);
} catch (TransporterException $e) {
    $e->getMessage(); // The underlying HTTP client's error message
}
```

This wraps the PSR-18 `ClientExceptionInterface` from your HTTP client. The original exception is available via `getPrevious()`.

## Response parsing errors

If the API returns a response that can't be decoded as JSON, the client throws `Anthropic\Exceptions\UnserializableResponse`:

```php
use Anthropic\Exceptions\UnserializableResponse;

try {
    $response = $client->messages()->create([...]);
} catch (UnserializableResponse $e) {
    $e->getMessage(); // JSON decode error message
    $e->response;     // The raw PSR-7 response, if available
}
```

This is rare in normal operation. It typically indicates a proxy or middleware returned an unexpected response (HTML error page, empty body, etc.).

## Exception hierarchy

```
ErrorException (API errors)
├── RateLimitException (HTTP 429)
TransporterException (HTTP client/network errors)
UnserializableResponse (JSON decode failures)
```

A practical catch block that covers everything:

```php
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnserializableResponse;

try {
    $response = $client->messages()->create([...]);
} catch (RateLimitException $e) {
    // Back off and retry
} catch (ErrorException $e) {
    // API returned an error (bad request, auth failure, overloaded, etc.)
} catch (TransporterException $e) {
    // Network or HTTP client failure
} catch (UnserializableResponse $e) {
    // Response wasn't valid JSON
}
```
