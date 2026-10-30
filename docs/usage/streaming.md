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

## Just the text

`text()` yields only the text deltas, so a chat UI doesn't have to pick them out of the events:

```php
foreach ($stream->text() as $delta) {
    echo $delta; // 'Okay', ',', ' let', "'s" ...
}
```

## The whole message

`finalMessage()` reads the rest of the stream and gives you the message as the API would have sent it without streaming: a `CreateResponse` with every block filled in, tool inputs decoded, and the stop reason and usage from `message_delta`.

```php
$message = $stream->finalMessage();

$message->content[1]->input; // ['location' => 'San Francisco, CA']
$message->stop_reason;       // 'tool_use'
$message->usage->outputTokens; // 89

$stream->finalText(); // "Okay, let's check the weather for San Francisco, CA:"
```

`finalText()` joins the text blocks as they are, since a block split at a citation keeps its own spacing. It's an empty string when there's no text. You can read part of the stream yourself first: `finalMessage()` goes on from where you stopped. Its content keeps every key the stream sent and adds none, so it can go straight back into the next request.

`currentMessage()` is the message so far, or `null` before `message_start`. Mid-stream its `stop_reason` is `null`, and a tool call's `input` is empty until its JSON is whole:

```php
foreach ($stream as $event) {
    if ($event->type === 'content_block_delta' && $event->delta->type === 'text_delta') {
        $draft = $stream->currentMessage()?->content[$event->index]->text;
    }
}
```

A stream that ended before `message_stop` gives what it had, and `completed()` returns `false`. One with no `message_start` at all throws a `LogicException`. After a server-side fallback, the message's `model` is the model that answered, as in a response that isn't streamed.

## Callbacks

Callbacks run as each event is read, before your loop gets it, so they also work when you only call `finalMessage()`:

```php
use Anthropic\Responses\Messages\CreateResponseContent;

$message = $stream
    ->onText(fn (string $delta) => print($delta))
    ->onContentBlock(fn (CreateResponseContent $block) => $block->type === 'tool_use' ? $tools->queue($block) : null)
    ->finalMessage();
```

There's `onEvent()` (each event object), `onText()` and `onThinking()` (each delta), `onContentBlock()` (each block once it stops, a tool call with its `input` decoded) and `onMessage()` (the whole message at `message_stop`). A callback only sees what's read after you add it. To get the message so far inside a text callback, call `currentMessage()`: a callback isn't handed a fresh copy on every delta, and it can't read the stream it's called from (that throws a `LogicException`).

## Folding events yourself

The same folding is a class of its own, `MessageAccumulator`, for events you read another way, like a recorded SSE stream or one relayed through a queue. Give it each event as the API sent it, the `data:` of each SSE event decoded as an array. An event object's `toArray()` has another shape, so save the raw `data:` instead:

```php
use Anthropic\Responses\Messages\MessageAccumulator;

$accumulator = new MessageAccumulator;

foreach ($storedEvents as $event) {
    $accumulator->add(json_decode($event, true));
}

$accumulator->message();   // CreateResponse, or null before message_start
$accumulator->completed(); // true once message_stop was added
```

It skips what it can't place, instead of throwing: an event before `message_start`, a second `message_start`, a block that skips an index, a delta for a block that never started, and event types it doesn't know.

A framework that adds methods to streams can extend `StreamResponse` and hand any stream to its class, callbacks included, with `$stream->as(MyStream::class)`, before reading it (after, it throws a `LogicException`). The Laravel package does this for its browser responses.

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

## Knowing when a stream ends

`withStreamCompletionHandler()` on the factory gets every Messages stream once it's done with, so you can record what each one used:

```php
use Anthropic\Responses\Messages\StreamResponse;

$client = Anthropic::factory()
    ->withApiKey(getenv('ANTHROPIC_API_KEY'))
    ->withStreamCompletionHandler(function (StreamResponse $stream) {
        $usage = $stream->usage();   // ['input_tokens' => 25, 'output_tokens' => 15]

        $stream->model();            // 'claude-sonnet-5-5'
        $stream->completed();        // true when it was read to its message_stop
        $stream->durationMs();       // from the response's headers until now
        $stream->meta()->requestId;  // 'req_018EeWyXxfu5pfWkrYcMdjWG'
    })
    ->make();
```

It runs at most once per stream: at `message_stop`, just before a mid-stream error is thrown, or when a stream you stopped reading is dropped (a `break`, an exception in your loop). The usage is `message_start`'s with the last `message_delta`'s counts over it. The real output count only comes with that final `message_delta`, so a stream dropped before it has its input tokens and `message_start`'s placeholder output count, usually 1. A stream you never read reports when it's dropped, with no usage, and a fatal error skips the handler. After a server-side fallback, `model()` is the model that answered. A handler that throws at `message_stop` throws out of your loop; on a mid-stream error the API's exception wins, and on a dropped stream the handler's exception is dropped, since a destructor can't fail your code. The same methods work on any stream without a handler.

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
