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
        'model' => 'claude-sonnet-5-5',
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

`ErrorException` provides these methods:

| Method | Returns | Description |
|--------|---------|-------------|
| `getMessage()` | `string` | The error message from the API, or the first line of a non-API error body |
| `getErrorType()` | `?string` | The error type (e.g., `invalid_request_error`, `authentication_error`, `overloaded_error`) |
| `getStatusCode()` | `int` | The HTTP status code |
| `getRequestId()` | `?string` | The failed request's ID, from the error body's `request_id` or the `request-id` header |
| `getErrorDetails()` | `?array` | The error's `details` object, such as `['error_code' => 'enforced_spend_limit_reached']` |
| `getRetryAfter()` | `?int` | The seconds the `retry-after` header asks you to wait, from a number or an HTTP date |

Quote `getRequestId()` when you contact support about a failure.

The raw PSR-7 response is also available on the `response` property if you need to inspect headers or the full body:

```php
$e->response; // Psr\Http\Message\ResponseInterface or null
```

> **Note:** For streamed requests, the status code might be `200` even when an error occurs, because the HTTP response started successfully before the error happened mid-stream. The client then picks the exception class from the error type, so an overload mid-stream is an `OverloadedException` too. Match errors by class or with `getErrorType()` (for example `'overloaded_error'`), not by the status code.

### Error classes

Each error status throws its own subclass of `ErrorException`, so you can catch one kind without inspecting the exception. Every subclass is an `ErrorException`, so a `catch (ErrorException $e)` still catches them all:

| Status | Error type | Exception | Typical cause |
|--------|-----------|-----------|---------------|
| 400 | `invalid_request_error` | `BadRequestException` | Bad parameters, missing required fields |
| 401 | `authentication_error` | `AuthenticationException` | Invalid or missing API key |
| 402 | `billing_error` | `BillingException` | Issue with billing or payment information |
| 403 | `permission_error` | `PermissionDeniedException` | API key doesn't have access to the requested resource |
| 404 | `not_found_error` | `NotFoundException` | Invalid model name or resource ID |
| 409 | `conflict_error` | `ConflictException` | The request conflicts with the resource's current state |
| 413 | `request_too_large` | `RequestTooLargeException` | Request exceeds the maximum allowed size (128 MB for Messages and Token Counting, 256 MB for Batches, on the Claude API; cloud platforms set their own limits) |
| 422 | | `UnprocessableEntityException` | The request was well formed but couldn't be processed |
| 429 | `rate_limit_error` | `RateLimitException` | Too many requests (see below) |
| 500 | `api_error` | `InternalServerException` | Server-side issue (other 5xx statuses too) |
| 504 | `timeout_error` | `ApiTimeoutException` | Request timed out while processing; consider using [streaming](../usage/streaming.md) |
| 529 | `overloaded_error` | `OverloadedException` | API is temporarily overloaded |

All of them live in `Anthropic\Exceptions`. A status without its own class, such as 405, throws a plain `ErrorException`.

```php
use Anthropic\Exceptions\NotFoundException;
use Anthropic\Exceptions\OverloadedException;

try {
    $response = $client->models()->retrieve($modelId);
} catch (NotFoundException $e) {
    // the model ID doesn't exist
} catch (OverloadedException $e) {
    // try again later
}
```

### Log context

`context()` returns what a log entry needs about the error: the request ID, the status, the error type, the `retry-after` seconds and the error code, leaving out what the error doesn't carry:

```php
$e->context();
// a rate limit: ['anthropic_request_id' => 'req_018EeWyXxfu5pfWkrYcMdjWG', 'status' => 429, 'type' => 'rate_limit_error', 'retry_after' => 30]
// the spend cap: ['anthropic_request_id' => 'req_018EeWyXxfu5pfWkrYcMdjWG', 'status' => 429, 'type' => 'rate_limit_error', 'error_code' => 'enforced_spend_limit_reached']
```

Laravel's exception handler adds it to the log entry of every reported exception, as do the Laravel packages of Sentry and Flare, so a logged API error carries its request ID with no code of yours. Elsewhere, pass it to your logger yourself: `$logger->error($e->getMessage(), $e->context())`. It holds only those five values, never the message, the other headers or a body. For an error in the middle of a stream, `status` is 200, as `getStatusCode()` is, so match on `type`.

### Retrying

With `withMaxRetries()` on the factory (see [Configuration](./configuration.md#retries)), the client retries connection failures, timeouts, 408, 409, 429 and 5xx responses itself, waiting what the API asks, so these exceptions reach you only once the retries run out.

## Rate limit errors

HTTP 429 responses throw `Anthropic\Exceptions\RateLimitException`, a subclass of `ErrorException`. Since it extends `ErrorException`, existing `catch (ErrorException $e)` blocks will catch it too. If you want to handle rate limits specifically, catch it first:

```php
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\ErrorException;

try {
    $response = $client->messages()->create([...]);
} catch (RateLimitException $e) {
    // Rate limited: wait as long as the API asks, then retry
    sleep($e->getRetryAfter() ?? 60);
} catch (ErrorException $e) {
    // All other API errors
}
```

`getRetryAfter()` reads the `Retry-After` header: how many seconds to wait before retrying. Not every 429 carries it: once the organization reaches its tier's monthly spend cap, the 429 has no `Retry-After`, since retrying won't help until access resumes. Its details say so:

```php
$e->getErrorDetails(); // ['error_code' => 'enforced_spend_limit_reached']
$e->getRetryAfter();   // null
```

A spend limit you set yourself on a workspace usually returns a 400 instead.

The other headers are on the PSR-7 `response` property; to read them as typed values, build a `MetaInformation` from it:

```php
use Anthropic\Responses\Meta\MetaInformation;

$meta = MetaInformation::from($e->response?->getHeaders() ?? []);

$meta->requestLimit?->remaining;
```

`$e->response` is set on every `ErrorException` the client throws. It's `null` only on one you build yourself with a status code, as in older tests, hence the `?->`.

For more on rate limits, see [Meta Information](./meta-information.md), which covers how to read your current limits and remaining quota from response headers.

## HTTP transport errors

When the HTTP request itself fails and no response comes back, the client throws `Anthropic\Exceptions\TransporterException`, or one of its subclasses:

- `ConnectionException`: the HTTP client reports a network failure, such as DNS resolution, a refused or reset connection, or a failed TLS handshake.
- `TimeoutException`, a `ConnectionException` too: the HTTP client gave up waiting, on the connection or the response.

```php
use Anthropic\Exceptions\TimeoutException;
use Anthropic\Exceptions\TransporterException;

try {
    $response = $client->messages()->create([...]);
} catch (TimeoutException $e) {
    // raise the HTTP client's timeout, or stream the response
} catch (TransporterException $e) {
    $e->getMessage(); // The underlying HTTP client's error message
}
```

These wrap the PSR-18 `ClientExceptionInterface` from your HTTP client, and the original exception is available via `getPrevious()`. A 504 from the API is an `ApiTimeoutException`, an `ErrorException`: the transport `TimeoutException` covers only your HTTP client giving up. A timeout is recognized by the HTTP client's message (cURL's "timed out", Symfony's "Idle timeout" and "Max duration"); any other client failure stays a plain `TransporterException`.

## Response parsing errors

If a successful (2xx) response can't be decoded as JSON, the client throws `Anthropic\Exceptions\UnserializableResponse`:

```php
use Anthropic\Exceptions\UnserializableResponse;

try {
    $response = $client->messages()->create([...]);
} catch (UnserializableResponse $e) {
    $e->getMessage(); // JSON decode error message
    $e->response;     // The raw PSR-7 response, if available
}
```

This is rare in normal operation. It typically means a proxy or middleware answered with something other than the API's JSON. An error status (400 and above) never ends up here: a proxy's HTML error page or an empty error body throws `ErrorException` with that status, `getErrorType()` returning `null`, and the first line of the page's text (or the HTTP reason phrase, such as `Bad Gateway`) as the message.

## Exception hierarchy

```
ErrorException (API errors)
├── BadRequestException (400)
├── AuthenticationException (401)
├── BillingException (402)
├── PermissionDeniedException (403)
├── NotFoundException (404)
├── ConflictException (409)
├── RequestTooLargeException (413)
├── UnprocessableEntityException (422)
├── RateLimitException (429)
├── InternalServerException (500 and other 5xx)
├── ApiTimeoutException (504)
└── OverloadedException (529)
TransporterException (HTTP client/network errors)
└── ConnectionException
    └── TimeoutException
UnserializableResponse (a 2xx response that isn't valid JSON)
```

A practical catch block that covers everything:

```php
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnserializableResponse;

try {
    $response = $client->messages()->create([...]);
} catch (RateLimitException|OverloadedException $e) {
    // Back off and retry
} catch (ErrorException $e) {
    // API returned another error (bad request, auth failure, not found, etc.)
} catch (TransporterException $e) {
    // Network or HTTP client failure
} catch (UnserializableResponse $e) {
    // Response wasn't valid JSON
}
```

---

For the full error format and request size limits, see the [Errors reference](https://platform.claude.com/docs/en/api/errors) on the Anthropic docs.
