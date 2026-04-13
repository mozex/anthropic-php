---
title: Streaming
weight: 2
---

Streaming lets you receive Claude's response as it's generated, token by token. Instead of waiting for the full response, you get incremental updates. This is useful for building chat interfaces where you want to show text as it appears.

## Basic streaming

Use `createStreamed()` instead of `create()`. The parameters are the same:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

foreach ($stream as $response) {
    echo $response->toArray()['type']; // event type
}
```

The stream returns a `StreamResponse` object that you can iterate over. Each iteration yields a `CreateStreamedResponse` with the event data.

## Event types

As you iterate, you'll receive events in this order:

| Event | When it fires | What it contains |
|-------|---------------|------------------|
| `message_start` | Once, at the start | Full message envelope (id, model, role) and initial usage |
| `content_block_start` | Start of each content block | Block type and index (e.g., `text`, `tool_use`, `thinking`) |
| `content_block_delta` | Multiple times per block | Incremental content (text chunks, JSON fragments, thinking) |
| `content_block_stop` | End of each content block | Block index |
| `message_delta` | Once, near the end | Stop reason and final usage |

The API also sends `message_stop` and `ping` events, but the client handles those internally. Your `foreach` loop only receives the five event types above. If an error occurs mid-stream, the client throws an `ErrorException` (see [Error Handling](../reference/error-handling.md)).

Here's a practical example that prints text as it arrives:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Tell me a short story.'],
    ],
]);

foreach ($stream as $response) {
    if ($response->type === 'content_block_delta'
        && $response->delta->type === 'text_delta') {
        echo $response->delta->text;
    }
}
```

## Full event sequence

Here's what a complete streamed text response looks like when you call `toArray()` on each event:

```php
// 1. message_start
[
    'type' => 'message_start',
    'message' => [
        'id' => 'msg_01SX1jLtTXgtJwB2EpSRNutG',
        'type' => 'message',
        'role' => 'assistant',
        'content' => [],
        'model' => 'claude-sonnet-4-6',
        'stop_reason' => null,
        'stop_sequence' => null,
    ],
    'usage' => [
        'input_tokens' => 9,
        'output_tokens' => 1,
        'cache_creation_input_tokens' => null,
        'cache_read_input_tokens' => null,
    ],
]

// 2. content_block_start
[
    'type' => 'content_block_start',
    'index' => 0,
    'content_block_start' => [
        'type' => 'text',
        'text' => '',
    ],
]

// 3-N. content_block_delta (repeated for each chunk)
[
    'type' => 'content_block_delta',
    'index' => 0,
    'delta' => [
        'type' => 'text_delta',
        'text' => 'Hello',
    ],
]

// Final: message_delta
[
    'type' => 'message_delta',
    'delta' => [
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
    ],
    'usage' => [
        'input_tokens' => null,
        'output_tokens' => 12,
        'cache_creation_input_tokens' => null,
        'cache_read_input_tokens' => null,
    ],
]
```

## Streaming with tool use

When Claude calls a tool during streaming, you'll see `tool_use` content blocks with `input_json_delta` events. The tool input arrives as partial JSON fragments that you'd concatenate:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get the current weather in a given location',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and state, e.g. San Francisco, CA',
                    ],
                ],
                'required' => ['location'],
            ],
        ],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What is the weather like in San Francisco?'],
    ],
]);

foreach ($stream as $response) {
    $response->toArray();
}
```

The tool use events look like this:

```php
// Tool block start
[
    'type' => 'content_block_start',
    'index' => 1,
    'content_block_start' => [
        'id' => 'toolu_01RDFRXpbNUGrZ1xQy443s5Q',
        'type' => 'tool_use',
        'name' => 'get_weather',
        'input' => [],
    ],
]

// Tool input arrives as JSON fragments
[
    'type' => 'content_block_delta',
    'index' => 1,
    'delta' => [
        'type' => 'input_json_delta',
        'partial_json' => '{"location": "San Francisco, CA"}',
    ],
]
```

See [Tool Use](./tool-use.md) for the full tool call workflow.

## Streaming with thinking

When [extended thinking](./thinking.md) is enabled, the stream includes `thinking` content blocks before the text response:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'adaptive',
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What is the greatest common divisor of 1071 and 462?'],
    ],
]);

foreach ($stream as $response) {
    // Thinking block start
    $response->content_block_start->type; // 'thinking'

    // Thinking content arrives incrementally
    $response->delta->type;     // 'thinking_delta'
    $response->delta->thinking; // 'I need to find the GCD using the Euclidean algorithm...'

    // Signature sent before thinking block closes
    $response->delta->type;      // 'signature_delta'
    $response->delta->signature; // 'EqQBCgIYAhIM1gbcDa9GJwZA2b3hGgxBdjrkzLoky3dl...'

    // Then text content follows
    $response->delta->type; // 'text_delta'
    $response->delta->text; // 'The greatest common divisor of 1071 and 462 is **21**.'
}
```

When using `'display' => 'omitted'`, no `thinking_delta` events are emitted. You'll only get the `signature_delta` followed by text deltas, which gives a faster time-to-first-text-token.

## Meta information on streams

You can access rate limit headers and request metadata on the stream object itself:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

$stream->meta(); // MetaInformation object with rate limits
```

See [Meta Information](../reference/meta-information.md) for details on what's available.
