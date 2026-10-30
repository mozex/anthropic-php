---
title: Streaming
weight: 2
---

Streaming lets you receive Claude's response as it's generated, token by token. Instead of waiting for the full response, you get incremental updates. This is useful for building chat interfaces where you want to show text as it appears.

## Basic streaming

Use `createStreamed()` instead of `create()`. The parameters are the same:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-5-5',
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
| `message_delta` | Once, near the end | Stop reason, `stop_details` on a refusal, the code execution container, and final usage |

The client skips `ping` events and ends the loop at `message_stop`, which your loop doesn't receive. Any other event type reaches your loop, including types the API adds later, so check `$response->type` rather than assuming this table is complete. If an error occurs mid-stream, the client throws an `ErrorException` (see [Error Handling](../reference/error-handling.md)).

Here's a practical example that prints text as it arrives:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-5-5',
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
        'model' => 'claude-sonnet-5-5',
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
    'model' => 'claude-sonnet-5-5',
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
    'model' => 'claude-opus-5-5',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'adaptive',
        'display' => 'summarized',
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

With `'display' => 'omitted'`, the default on Claude 5 models, the thinking text isn't streamed: any `thinking_delta` that arrives has an empty `thinking`, and the `signature_delta` and the text deltas follow. That gives a faster time to the first text token. The thinking block start already carries a `signature` field, empty until the delta fills it:

```php
$response->content_block_start->signature; // ''
```

To show progress while the model thinks with its text omitted, the `thinking-token-count-2026-05-13` beta can add an `estimated_tokens` count to a `thinking_delta`, whose `thinking` stays empty. It's sent only with `display` set to `omitted`, and not every delta carries one, so treat `null` as no estimate:

```php
$response->delta->type;             // 'thinking_delta'
$response->delta->thinking;         // ''
$response->delta->estimated_tokens; // an integer, or null
```

With thinking enabled, the final `message_delta` usage also carries the thinking-token breakdown:

```php
$response->usage->outputTokensDetails?->thinkingTokens; // 150
```

## Streaming with compaction

With [threshold compaction](./messages.md#compaction-at-a-threshold) enabled, a compacted conversation opens with a `compaction` content block. Unlike text, the whole summary arrives in a single delta:

```php
// content_block_start
$response->content_block_start->type; // 'compaction'

// One content_block_delta carrying the full summary
$response->delta->type;    // 'compaction_delta'
$response->delta->content; // 'Summary of the conversation: ...'
```

A `content_block_stop` follows, then the regular blocks begin.

[Compaction on demand](./messages.md#compaction-on-demand) works differently: the block arrives whole in its `content_block_start` event, with no deltas, so `content` there is the summary string and `signature` is set:

```php
$response->content_block_start->type;      // 'compaction'
$response->content_block_start->content;   // 'Summary of the conversation: ...'
$response->content_block_start->signature; // 'EuYBCkQY...'
```

The `fallback` block and an `advisor_tool_result` arrive whole the same way. A block start types every field the block can carry, as the non-streamed content blocks do: `caller`, `toolset_name`, `server_name`, `is_error`, `citations`, `encrypted_content` and the rest.

## Refusals while streaming

A refusal can arrive before any output, or after part of the answer has streamed. Either way, the `message_delta` event carries `stop_reason: 'refusal'` and the `stop_details` that explain it:

```php
$text = '';

foreach ($stream as $response) {
    if ($response->type === 'content_block_delta' && $response->delta->type === 'text_delta') {
        $text .= $response->delta->text;
    }

    if ($response->type === 'message_delta' && $response->delta->stop_reason === 'refusal') {
        $response->delta->stop_details?->category;    // 'cyber'
        $response->delta->stop_details?->explanation; // 'This request was declined because it could enable cyber harm.'

        $text = ''; // the partial answer is incomplete: discard it
    }
}
```

Treat any text already streamed as incomplete and drop it, from your screen too. The [Messages page](./messages.md#handling-refusals) covers the categories, billing and what to do next.

## Other fields on stream events

The `message_start` event's message carries the fields known when the response begins: `diagnostics` (see [cache diagnostics](./messages.md#cache-diagnostics)), and, under the matching betas, `context_management` and `input_transformations`. `container` is `null` there. The `message_delta` event carries the rest:

```php
// message_delta
$response->delta->container;      // ['id' => 'container_...', 'expires_at' => '...'] once code execution ran
$response->context_management;    // ['applied_edits' => [...]] under the context editing beta
$response->input_transformations; // set only after a fallback in the middle of the stream
```

Keep the `container` ID to reuse the sandbox on the next request, as [Server Tools](./server-tools.md#container-persistence) shows.

## Meta information on streams

You can access rate limit headers and request metadata on the stream object itself:

```php
$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

$stream->meta(); // MetaInformation object with rate limits
```

See [Meta Information](../reference/meta-information.md) for details on what's available.

---

For the full event format and streaming specification, see the [Streaming guide](https://platform.claude.com/docs/en/build-with-claude/streaming) on the Anthropic docs.
