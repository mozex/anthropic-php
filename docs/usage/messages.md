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

---

For the full list of parameters, content block types, and the latest API changes, see the [Messages API reference](https://platform.claude.com/docs/en/api/messages/create) on the Anthropic docs.
