---
title: Meta Information
weight: 3
---

Every API response includes HTTP headers with rate limit data, request IDs, and token limits. The client exposes these through the `meta()` method on response objects.

## Accessing meta information

Call `meta()` on any response:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);

$meta = $response->meta();
```

## Request ID

Every request gets a unique identifier:

```php
$meta->requestId; // 'req_012nTzj6kLoP8vZ1SGANvcgR'
```

Include this when contacting support about a specific request.

## Rate limits

The API enforces rate limits on both requests per minute and tokens per minute. The `meta()` object tells you where you stand:

### Request limits

```php
$meta->requestLimit->limit;     // 3000 (max requests per period)
$meta->requestLimit->remaining; // 2999 (requests left)
$meta->requestLimit->reset;     // '2024-05-01T13:29:17Z' (when the limit resets)
```

### Token limits

```php
$meta->tokenLimit->limit;     // 250000
$meta->tokenLimit->remaining; // 249984
$meta->tokenLimit->reset;     // '2024-05-01T13:29:17Z'
```

### Input and output token limits

These are tracked separately from the combined token limit:

```php
$meta->inputTokenLimit->limit;      // 20000
$meta->inputTokenLimit->remaining;  // 19500
$meta->inputTokenLimit->reset;      // '2024-05-01T13:29:17Z'

$meta->outputTokenLimit->limit;     // 5000
$meta->outputTokenLimit->remaining; // 4900
$meta->outputTokenLimit->reset;     // '2024-05-01T13:29:17Z'
```

Each limit object has the same three fields: `limit` (the maximum), `remaining` (how much is left), and `reset` (when it resets, as an ISO 8601 timestamp).

## Raw header format

The `toArray()` method returns the meta information using the original HTTP header names:

```php
$meta->toArray();

// [
//   'anthropic-ratelimit-requests-limit' => 3000,
//   'anthropic-ratelimit-tokens-limit' => 250000,
//   'anthropic-ratelimit-requests-remaining' => 2999,
//   'anthropic-ratelimit-tokens-remaining' => 249983,
//   'anthropic-ratelimit-requests-reset' => '2024-05-01T13:29:17Z',
//   'anthropic-ratelimit-tokens-reset' => '2024-05-01T13:29:17Z',
//   'anthropic-ratelimit-input-tokens-limit' => 20000,
//   'anthropic-ratelimit-input-tokens-remaining' => 19500,
//   'anthropic-ratelimit-input-tokens-reset' => '2024-05-01T13:29:17Z',
//   'anthropic-ratelimit-output-tokens-limit' => 5000,
//   'anthropic-ratelimit-output-tokens-remaining' => 4900,
//   'anthropic-ratelimit-output-tokens-reset' => '2024-05-01T13:29:17Z',
//   'request-id' => 'req_012nTzj6kLoP8vZ1SGANvcgR',
// ]
```

## Custom headers

Non-standard headers (anything not covered by the typed properties above) are available via the `custom` property:

```php
$meta->custom; // additional headers as a key-value object
```

## Streaming responses

For streamed responses, call `meta()` on the stream object (not on individual events):

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);

$stream->meta(); // same MetaInformation object
```

The meta information comes from the HTTP response headers, which are set at the start of the stream.

## Batch results

Batch result responses also support `meta()`:

```php
$results = $client->batches()->results('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

// Process results...
foreach ($results as $individual) {
    // ...
}

$results->meta(); // rate limit headers for this request
```

## Handling rate limits

For details on catching rate limit errors and implementing retry logic, see [Error Handling](./error-handling.md). The Anthropic documentation on [rate limits](https://platform.claude.com/docs/en/api/rate-limits) covers the full details on limit tiers and how they work.
