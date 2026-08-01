---
title: Messages
weight: 1
---

The Messages API is the primary way to interact with Claude through this SDK. You send a list of messages (a conversation), and Claude responds with a new message.

## Creating a message

Pass an array of parameters to `create()`. At minimum, you need a `model`, `max_tokens` limit, and at least one message:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);
```

The `messages` array represents a conversation. Each message has a `role` (`user` or `assistant`) and `content` (a string or an array of content blocks).

## Response structure

The `create()` method returns a `CreateResponse` object. Here's what you get back:

```php
$response->id;            // 'msg_01BSy0WCV7QR2adFBauynAX7'
$response->type;          // 'message'
$response->role;          // 'assistant'
$response->model;         // 'claude-sonnet-4-6'
$response->stop_reason;   // 'end_turn'
$response->stop_sequence; // null
```

### Content blocks

The response content is an array of typed blocks. For a simple text response, there's one block:

```php
foreach ($response->content as $block) {
    $block->type; // 'text'
    $block->text; // 'Hello! It's nice to meet you. How can I assist you today?'
}
```

Different features produce different content block types. [Tool Use](./tool-use.md) adds `tool_use` blocks. [Thinking](./thinking.md) adds `thinking` blocks. [Server Tools](./server-tools.md) add `server_tool_use` and result blocks.

### Token usage

Every response includes token counts:

```php
$response->usage->inputTokens;               // 10
$response->usage->outputTokens;              // 19
$response->usage->cacheCreationInputTokens;  // 0
$response->usage->cacheReadInputTokens;      // 0
```

If you're using [prompt caching](https://platform.claude.com/docs/en/build-with-claude/prompt-caching), the cache fields tell you how many tokens were written to or read from cache.

For cache breakdowns by window type:

```php
$response->usage->cacheCreation; // null or CreateResponseUsageCacheCreation
$response->usage->cacheCreation?->ephemeral5mInputTokens;  // 456
$response->usage->cacheCreation?->ephemeral1hInputTokens;  // 100
```

The `serviceTier` field shows which processing tier handled the request:

```php
$response->usage->serviceTier; // 'standard', 'priority', 'batch', or null
```

When using [server tools](./server-tools.md), you can check tool usage counts:

```php
$response->usage->serverToolUse; // null or CreateResponseUsageServerToolUse
$response->usage->serverToolUse?->webSearchRequests; // 3
```

With [thinking](./thinking.md) enabled, `outputTokensDetails` breaks out how many of the billed output tokens went to reasoning:

```php
$response->usage->outputTokensDetails?->thinkingTokens; // 150
```

On [fast mode](https://platform.claude.com/docs/en/build-with-claude/fast-mode) requests, `speed` reports which speed actually served the response. This matters on models where a fast request silently runs at standard speed instead of erroring:

```php
$response->usage->speed; // 'fast', 'standard', or null
```

### Converting to an array

Every response object has a `toArray()` method that returns the raw data as a PHP array:

```php
$response->toArray();
// ['id' => 'msg_01BSy0WCV7QR2adFBauynAX7', 'type' => 'message', ...]
```

This is useful for logging, serialization, or when you need to pass the response to code that expects plain arrays.

## Multi-turn conversations

To have a back-and-forth conversation, include the full message history in each request. The API is stateless, so you need to send the entire conversation every time:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'What is PHP?'],
        ['role' => 'assistant', 'content' => 'PHP is a server-side scripting language...'],
        ['role' => 'user', 'content' => 'What version should I use?'],
    ],
]);
```

Messages must alternate between `user` and `assistant` roles. The conversation always starts with a `user` message.

## System messages

Use the `system` parameter to give Claude instructions, persona, or context that applies to the whole conversation:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'system' => 'You are a helpful PHP expert. Answer concisely.',
    'messages' => [
        ['role' => 'user', 'content' => 'What is the null coalescing operator?'],
    ],
]);
```

The system message isn't part of the `messages` array. It's a separate top-level parameter.

## Vision

Claude can read images in the same request as text. Instead of a string, pass an array of content blocks in the message's `content`:

```php
$imagePath = '/path/to/photo.jpg';

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'What is in this image?',
                ],
                [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'image/jpeg',
                        'data' => base64_encode(file_get_contents($imagePath)),
                    ],
                ],
            ],
        ],
    ],
]);
```

Supported media types are `image/jpeg`, `image/png`, `image/gif`, and `image/webp`. You can detect the MIME type from the file itself with PHP's `finfo`:

```php
$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($imagePath);
```

You can also pass images by URL instead of embedding them:

```php
[
    'type' => 'image',
    'source' => [
        'type' => 'url',
        'url' => 'https://example.com/photo.jpg',
    ],
]
```

Multiple images in a single message work too. Add as many image blocks as you need to the content array alongside the text block.

## Tracking users

Pass a `metadata` object with a `user_id` to associate each request with a user in your system. These IDs appear in the Anthropic Console for analytics and abuse detection:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'metadata' => [
        'user_id' => $user->uuid,
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);
```

Use an opaque identifier like a UUID or hash. Don't send personal information like names or email addresses.

## Passing parameters

This client doesn't validate or transform request parameters. Whatever you pass in the array goes directly to the Anthropic API as JSON. This means:

- New API parameters work immediately, even before the client adds explicit support
- You can pass any parameter documented in the [Anthropic API reference](https://platform.claude.com/docs/en/api/messages/create)
- If you pass an invalid parameter, the API returns an error (see [Error Handling](../reference/error-handling.md))

For example, setting temperature and top_p:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'temperature' => 0.7,
    'top_p' => 0.9,
    'messages' => [
        ['role' => 'user', 'content' => 'Write a haiku about PHP.'],
    ],
]);
```

## Stop sequences

You can tell Claude to stop generating when it produces a specific string:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'stop_sequences' => ['```'],
    'messages' => [
        ['role' => 'user', 'content' => 'Write a PHP function, then explain it.'],
    ],
]);

$response->stop_reason;   // 'stop_sequence'
$response->stop_sequence; // '```'
```

When Claude hits a stop sequence, `stop_reason` is `'stop_sequence'` and `stop_sequence` tells you which one matched.

## Handling refusals

When safety classifiers intervene mid-generation, the API returns `stop_reason: 'refusal'` along with a `stop_details` object that explains why. The response is otherwise well-formed, so you can treat it like any other completion and just branch on `stop_reason`:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => $userPrompt],
    ],
]);

if ($response->stop_reason === 'refusal') {
    $response->stop_details->type;        // 'refusal'
    $response->stop_details->category;    // 'cyber', 'bio', 'frontier_llm', 'reasoning_extraction', 'general_harms', or null
    $response->stop_details->explanation; // human-readable text, or null
}
```

`stop_details` is only populated on refusal responses; on a normal completion it's `null`. The `explanation` text isn't guaranteed to be stable between requests, so don't parse it. Treat `category` as the machine-readable signal and show `explanation` to the user if you need to display something.

### Falling back to another model

On Claude Fable 5 and Opus 5, the API can retry a refused request on another model instead of handing you the refusal. This is in beta. Pass the `fallbacks` parameter with the matching beta header:

```php
$response = $client->messages()->create([
    'model' => 'claude-fable-5',
    'max_tokens' => 1024,
    'fallbacks' => 'default',
    'betas' => ['server-side-fallback-2026-07-01'],
    'messages' => [
        ['role' => 'user', 'content' => $userPrompt],
    ],
]);
```

The `'default'` mode routes by refusal category, so you never maintain a model list. If you'd rather name the substitutes yourself, pass an array like `[['model' => 'claude-opus-4-8']]` with the `server-side-fallback-2026-06-01` header instead.

When a fallback served the request, the response tells you in three places:

```php
// The model that actually answered
$response->model; // 'claude-opus-4-8'

// A fallback content block marks the handoff
$response->content[0]->type;        // 'fallback'
$response->content[0]->from->model; // 'claude-fable-5'
$response->content[0]->to->model;   // 'claude-opus-4-8'

// usage.iterations records every attempt
foreach ($response->usage->iterations ?? [] as $iteration) {
    $iteration->type;         // 'message' (declined) or 'fallback_message' (served)
    $iteration->model;        // which model ran this attempt
    $iteration->inputTokens;  // billed per attempt, at that model's rates
    $iteration->outputTokens;
}
```

Two edge cases worth knowing. A sticky-served turn (the API remembers this conversation already fell back and routes straight to the fallback model) carries no `fallback` block; detect it by the `fallback_message` entry in `usage->iterations`. And when the fallback model was rate limited, the refusal comes back as-is with `stop_details->recommended_model` naming a model you can retry directly:

```php
$response->stop_details?->recommended_model; // 'claude-opus-4-8' or null
```

On the next turn, echo the assistant content back with the `fallback` block exactly where it appeared. The API uses its position to validate the surrounding blocks.

## The `pause_turn` stop reason

Long-running turns that hit internal limits come back with `stop_reason: 'pause_turn'` instead of `'end_turn'`. The response is valid and complete for the work done so far. To let Claude continue, send the same response back as the next assistant message:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 8192,
    'messages' => $messages,
]);

while ($response->stop_reason === 'pause_turn') {
    $messages[] = [
        'role' => 'assistant',
        'content' => array_map(fn ($block) => $block->toArray(), $response->content),
    ];

    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 8192,
        'messages' => $messages,
    ]);
}
```

You don't need any special parameter to resume, just echo the paused content back in the next turn. If you don't want to resume, leave it as is and treat it like a normal reply.

## Inference region

Responses include the geographic region that processed the request:

```php
$response->usage->inferenceGeo; // 'us', 'eu', or null
```

Useful for data-residency logging or routing decisions. The field is `null` when the API doesn't report a region.

## Server-side compaction

For conversations that grow toward the context window, the compaction beta (`compact-2026-01-12`) lets the API summarize earlier context into a `compaction` content block instead of failing. Enable it through `context_management`:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5',
    'max_tokens' => 4096,
    'betas' => ['compact-2026-01-12'],
    'context_management' => [
        'edits' => [
            ['type' => 'compact_20260112'],
        ],
    ],
    'messages' => $messages,
]);

foreach ($response->content as $block) {
    if ($block->type === 'compaction') {
        $block->content; // 'Summary of the conversation: ...'
    }
}
```

Send `compaction` blocks back unchanged on the next turn. The API uses them to replace the compacted history, so dropping the block, or keeping only its text, silently loses the compaction state.

Under this beta, responses also carry a `context_management` field listing which edits were applied, and `usage->iterations` entries appear even when nothing was compacted. With `pause_after_compaction` enabled, a turn that compacts stops with `stop_reason: 'compaction'` so you can resume on your own terms. Two practical notes from testing this live: the compaction trigger has a 50,000 input-token minimum, and iteration entries here carry no `model` field (unlike fallback iterations).

```php
$response->context_management; // ['applied_edits' => [...]] or null
$response->stop_reason;        // 'compaction' when paused after compacting
```

## Cache diagnostics

If your prompt cache hit rate drops and you don't know why, cache diagnostics tells you where two consecutive requests diverged. It's in beta behind the `cache-diagnosis-2026-04-07` header. Pass the previous response's `id`, and the API compares the two request fingerprints:

```php
$first = $client->messages()->create([
    'model' => 'claude-opus-5',
    'max_tokens' => 1024,
    'betas' => ['cache-diagnosis-2026-04-07'],
    'diagnostics' => ['previous_message_id' => null], // opt in on the first turn
    'messages' => $messages,
]);

$second = $client->messages()->create([
    'model' => 'claude-opus-5',
    'max_tokens' => 1024,
    'betas' => ['cache-diagnosis-2026-04-07'],
    'diagnostics' => ['previous_message_id' => $first->id],
    'messages' => $updatedMessages,
]);

$second->diagnostics?->cache_miss_reason?->type;                      // 'system_changed'
$second->diagnostics?->cache_miss_reason?->cache_missed_input_tokens; // 41850
```

`diagnostics` is `null` when nothing diverged. When it's set but `cache_miss_reason` is `null`, the comparison was still running when the response was built; check the next turn. The reason `type` is one of `model_changed`, `system_changed`, `tools_changed`, `messages_changed`, `previous_message_not_found`, or `unavailable`, and the four `*_changed` types include a `cache_missed_input_tokens` estimate of how much cacheable prefix was lost.

When streaming, `diagnostics` arrives on the `message_start` event's message object.

---

For the full list of parameters, content block types, and the latest API changes, see the [Messages API reference](https://platform.claude.com/docs/en/api/messages/create) on the Anthropic docs.
