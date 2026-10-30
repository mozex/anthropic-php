---
title: Messages
weight: 1
---

The Messages API is the primary way to interact with Claude through this SDK. You send a list of messages (a conversation), and Claude responds with a new message.

## Creating a message

Pass an array of parameters to `create()`. At minimum, you need a `model`, `max_tokens` limit, and at least one message:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
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
$response->model;         // 'claude-sonnet-5-5'
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

Different features produce different content block types. [Tool Use](./tool-use.md) adds `tool_use` blocks. [Thinking](./thinking.md) adds `thinking` blocks. [Server Tools](./server-tools.md) add `server_tool_use` and result blocks, and the MCP connector its `mcp_tool_*` blocks.

Every Claude 5 model thinks by default, so on a harder request the reply can start with a `thinking` block (its text empty unless you ask for it) before the answer. When you want the answer's text, collect the `text` blocks rather than assuming the answer is `content[0]`:

```php
$text = '';

foreach ($response->content as $block) {
    if ($block->type === 'text') {
        $text .= $block->text;
    }
}
```

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

`toArray()` returns every field the API sent, including ones this package doesn't type yet and fields sent as `null`, in the API's order. That makes `$response->toArray()['content']` safe to send back as the assistant turn of the next request, with thinking signatures, compaction blocks and tool calls intact. Array access reads the same data, so `$response['some_new_field']` works before a release types it.

One detail on the way back: in `toArray()`, a tool call without parameters has an `ArrayObject` as its `input`, which encodes as `{}`. A plain empty PHP array would encode as `"input": []`, which the API rejects. The typed `$block->input` is still `[]`.

## Multi-turn conversations

To have a back-and-forth conversation, include the full message history in each request. The API is stateless, so you need to send the entire conversation every time:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
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
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'system' => 'You are a helpful PHP expert. Answer concisely.',
    'messages' => [
        ['role' => 'user', 'content' => 'What is the null coalescing operator?'],
    ],
]);
```

The system message isn't part of the `messages` array. It's a separate top-level parameter.

### Mid-conversation system messages

On newer models you can also add instructions partway through a conversation, as a message with the `system` role inside `messages`. No beta header is needed. It works on Claude Fable 5.1, Mythos 5.1, Fable 5, Mythos 5, Opus 5.5, Opus 5, Sonnet 5.5, Haiku 5.5 and Opus 4.8, but not on Sonnet 5:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'system' => 'You are a helpful PHP expert.',
    'messages' => [
        ['role' => 'user', 'content' => 'Write a function that sums an array.'],
        ['role' => 'assistant', 'content' => 'function sum($items) { return array_sum($items); }'],
        ['role' => 'user', 'content' => 'Now one that averages it.'],
        ['role' => 'system', 'content' => 'From now on, every suggestion must include explicit type annotations.'],
    ],
]);
```

A `system` message can't be the first entry in `messages`. It goes right after a `user` turn (one carrying `tool_result` blocks counts) or after an `assistant` turn that ends in a server tool result, and before the next `assistant` turn or at the end of the array, never between a `tool_use` block and its `tool_result`. Once sent, keep it in the history unchanged: editing or removing it invalidates the prompt cache from that point.

Three betas extend these messages:

- **Turn-scoped instructions** (`mid-conversation-system-clear-at-2026-08-21`): `'clear_at' => 'next_user_message'` makes the message apply to one reply only. It takes text only. Keep sending it in the history as it was.
- **Effort per message** (`mid-conversation-output-config-2026-07-01`): `['role' => 'system', 'content' => [], 'output_config' => ['effort' => 'low']]` changes the [effort level](./thinking.md#effort-levels) from that point, on Fable 5.1, Mythos 5.1, Opus 5.5, Opus 5, Sonnet 5.5 and Haiku 5.5.
- **Tool changes** (`mid-conversation-tool-changes-2026-07-01` for changes by reference, which is also the header on Bedrock and Google Cloud; `inline-tools-2026-09-15` on the Claude API covers those and inline definitions): `tool_addition` and `tool_removal` blocks add or remove tools without editing `tools`, which would break the cache. They aren't accepted right after a paused `assistant` turn:

```php
$messages[] = [
    'role' => 'system',
    'content' => [
        ['type' => 'tool_removal', 'tool' => ['type' => 'tool_reference', 'name' => 'get_weather']],
    ],
];
```

With `inline-tools-2026-09-15`, a `tool_addition` can carry a whole definition (`'tool' => ['type' => 'tool_definition', 'definition' => [...]]`); a tool added by reference must already be in `tools`, either with `'defer_loading' => true` or withdrawn by an earlier `tool_removal`. When a later compaction summarizes these messages, the `compaction` block lists the changes in `$block->tool_changes`.

## Vision

Claude can read images in the same request as text. Instead of a string, pass an array of content blocks in the message's `content`:

```php
$imagePath = '/path/to/photo.jpg';

$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
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
    'model' => 'claude-sonnet-5-5',
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

For example, setting `temperature` on a model that still accepts it:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'temperature' => 0.7,
    'messages' => [
        ['role' => 'user', 'content' => 'Write a haiku about PHP.'],
    ],
]);
```

Sampling parameters are on their way out. Claude Fable 5.1, Mythos 5.1, Fable 5, Mythos 5, Opus 5.5, Opus 5, Opus 4.8, Opus 4.7, Sonnet 5.5, Sonnet 5 and Haiku 5.5 return a 400 for any non-default `temperature`, `top_p` or `top_k`, and for `temperature` and `top_p` sent together. Older models accept them while thinking is off, though Sonnet 4.5 and Haiku 4.5 reject `temperature` and `top_p` sent together. Under API version `2026-10-21` (see [Configuration](../reference/configuration.md#api-version)), all three are rejected on every model.

## Stop sequences

You can tell Claude to stop generating when it produces a specific string:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
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
    'model' => 'claude-sonnet-5-5',
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

`stop_details` is only populated on refusal responses; on a normal completion it's `null`. The `explanation` text isn't guaranteed to be stable between requests, so don't parse it. Treat `category` as the machine-readable signal and show `explanation` to the user if you need to display something. Both are `null` when a refusal doesn't map to a named category.

| Category | Billed when refused before any output |
|----------|---------------------------------------|
| `cyber` | No |
| `bio` | Yes |
| `frontier_llm` | Yes |
| `reasoning_extraction` | Yes |
| `general_harms` | No |

A refusal with a `null` category before any output isn't billed either. A refusal that arrives after some output bills the input and the output produced so far. `reasoning_extraction` comes from asking the model to put its reasoning into the answer, a JSON field or a tool input; it has no recommended fallback model, so change the prompt instead of retrying.

Sending the same conversation to the same model again usually gets another refusal. Remove or rephrase the turn that triggered it, or start the conversation over, before you continue. When you stream, the refusal arrives on the `message_delta` event, after any text already streamed; see [Streaming](./streaming.md#refusals-while-streaming).

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

// A fallback content block marks the handoff, and what triggered it
$response->content[0]->type;               // 'fallback'
$response->content[0]->from->model;        // 'claude-fable-5'
$response->content[0]->to->model;          // 'claude-opus-4-8'
$response->content[0]->trigger?->type;     // 'refusal'
$response->content[0]->trigger?->category; // 'cyber'

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

On the next turn, echo the assistant content back with the `fallback` block exactly where it appeared. The API uses its position to validate the surrounding blocks. Message Batches reject `fallbacks`, and Claude Haiku 5.5 has no server-side fallback.

When you retry a refusal on another model yourself, prompt caches don't carry over: the retry writes the whole conversation into the new model's cache, and cache writes cost more than reads. With the fallback credit beta (`fallback-credit-2026-07-01`), the refusal carries a token, and a retry that sends it back is billed as though the conversation had been on the new model from the start:

```php
$token = $response->stop_details?->fallback_credit_token;        // a string, or null without a credit
$prefill = $response->stop_details?->fallback_has_prefill_claim; // true: the retry may continue the partial output

$retry->usage->fallbackCredit; // the credit's status on the retry, or null
```

Send the token as the retry's top-level `fallback_credit_token` parameter, with the same beta header, within five minutes. The retry must keep the refused request's `system`, `messages`, `tools` and the other prompt fields exactly as they were; only the model and a few settings may change. The [fallback credit guide](https://platform.claude.com/docs/en/build-with-claude/fallback-credit) has the retry shapes and which fallback models each model accepts. Server-side fallback applies the credit for you. A Claude Haiku 5.5 refusal carries no credit.

### Falling back on the client

Where server-side fallback isn't available (Claude Haiku 5.5, or a platform without it), `RefusalFallbackMiddleware` does the same job from the client for requests that don't stream. It sends a refused request to the next model you list, redeems the refusal's fallback credit for you, and returns the answer with the same `fallback` block and `usage->iterations` a server-side fallback gives:

```php
use Anthropic\Middleware\FallbackState;
use Anthropic\Middleware\RefusalFallbackMiddleware;

$state = new FallbackState; // one per conversation

$client = Anthropic::factory()
    ->withApiKey('your-api-key')
    ->withMiddleware(new RefusalFallbackMiddleware(['claude-opus-4-8'], state: $state))
    ->make();

$response = $client->messages()->create([
    'model' => 'claude-fable-5',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => $userPrompt],
    ],
]);

$response->model; // 'claude-opus-4-8' when Claude Fable 5 refused
```

The models are tried in order, and each has to be one the refused model allows as a fallback (the [fallback credit guide](https://platform.claude.com/docs/en/build-with-claude/fallback-credit) lists them). An entry can also be an array that changes a few settings for its model, such as `['model' => 'claude-opus-4-8', 'max_tokens' => 2048]`: `max_tokens`, `thinking`, `output_config` (merged one field at a time, with `null` removing a field) and `speed`. Changing `thinking` or `output_config` breaks the match the credit needs, so that retry is billed in full; so does a request with `thinking: between_tools`, which goes to the fallback as `disabled` unless the entry sets `thinking`, since only Claude Sonnet 5.5 takes it. A request sent with `speed: fast` loses it on a fallback that's rate limited or overloaded, unless the entry sets `speed` itself. A model that refused is never asked again within the same request.

The shared `FallbackState` keeps the conversation on the model that answered, so the next turn doesn't ask a model that already refused; without one, every request starts from the model you asked for. Its `index` is `null` for the model you asked for and the chain position otherwise, so you can store it with the conversation and set it again on the next request. A pinned turn goes straight to its model, so it carries no `fallback` block: read `$response->model`. The second argument, `fn (array $refused, array $entry, int $index) => ...`, is called before each fallback, for logging; the arguments come in the official SDK's order.

With retries on (see [Configuration](../reference/configuration.md#retries)), a fallback that's overloaded or rate limited sends the whole request again, from the pinned model, so without a shared state the model that refused is asked once more, and each attempt can take a request timeout.

The middleware adds the `fallback-credit-2026-07-01` beta to the requests it handles, and takes the `fallback` blocks it put at the front of a turn out of the history you send back. It handles `messages()->create()` and raw `POST` requests to `messages`: a streamed request passes through unchanged (server-side `fallbacks` covers streams), and a request that sets `fallbacks` throws, since the API would run a chain of its own.

## The `pause_turn` stop reason

Long-running turns that hit internal limits come back with `stop_reason: 'pause_turn'` instead of `'end_turn'`. The response is valid and complete for the work done so far. To let Claude continue, send the same response back as the next assistant message:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 8192,
    'messages' => $messages,
]);

while ($response->stop_reason === 'pause_turn') {
    $messages[] = [
        'role' => 'assistant',
        'content' => array_map(fn ($block) => $block->toArray(), $response->content),
    ];

    $response = $client->messages()->create([
        'model' => 'claude-sonnet-5-5',
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

For conversations that grow toward the context window, the API can summarize earlier context into a `compaction` content block. There are two betas: compaction when the input passes a threshold, and compaction on demand.

### Compaction at a threshold

The `compact-2026-01-12` beta compacts automatically once the input passes a token count. Enable it through `context_management`:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
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

Send `compaction` blocks back unchanged on the next turn. The API uses them to replace the compacted history (it ignores every block before one), so dropping the block, or keeping only its text, silently loses the compaction state.

The trigger defaults to 150,000 input tokens and can't go below 50,000 (`'trigger' => ['type' => 'input_tokens', 'value' => 100000]`). Under this beta, responses also carry a `context_management` field listing which edits were applied, and `usage->iterations` entries appear even when nothing was compacted; these iteration entries carry no `model` field, unlike fallback iterations. With `pause_after_compaction` enabled, a turn that compacts stops with `stop_reason: 'compaction'` so you can resume on your own terms.

```php
$response->context_management; // ['applied_edits' => [...]] or null
$response->stop_reason;        // 'compaction' when paused after compacting
```

### Compaction on demand

The `compact-2026-09-04` beta compacts when you ask. Send the conversation with a top-level `compaction` parameter, and the reply is a single signed `compaction` block with `stop_reason: 'compaction'`:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 4096,
    'betas' => ['compact-2026-09-04'],
    'compaction' => ['type' => 'summarize'],
    'messages' => $messages,
]);

$block = $response->content[0];

$block->type;      // 'compaction'
$block->content;   // 'Summary of the conversation: the user is designing the data model for a recipe app...'
$block->signature; // 'EuYBCkQY...'
```

An optional `instructions` string replaces the summarizing prompt. A request can't combine `compaction` with `context_management`.

On every later request, send the block exactly as `toArray()` returns it, `signature` included, and the same beta header. Put it first in `messages` (an `assistant` message of its own, or the first block of the first message), and drop the messages it summarized:

```php
$messages = [
    ['role' => 'assistant', 'content' => [$block->toArray()]],
    ['role' => 'user', 'content' => 'Now suggest field names for Ingredient.'],
];
```

An edited block, an extra key, or a summarized message left in front of it returns a 400 with `error.details.error_code` such as `compaction_signature_invalid`, `compaction_content_mismatch` or `compaction_block_misplaced`. When the model didn't summarize, `content` is empty and `stop_reason` says why (`max_tokens`, `tool_use`, `refusal` and so on).

When you stream either kind, see [Streaming](./streaming.md#streaming-with-compaction): an on-demand block arrives whole.

## Cache diagnostics

If your prompt cache hit rate drops and you don't know why, cache diagnostics tells you where two consecutive requests diverged. It's generally available on the Claude API, with no beta header. Send a `diagnostics` object on every turn, with `previous_message_id` set to `null` on the first and to the previous response's `id` after that, and the API compares the two request fingerprints:

```php
$first = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 1024,
    'diagnostics' => ['previous_message_id' => null], // opt in on the first turn
    'messages' => $messages,
]);

$second = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 1024,
    'diagnostics' => ['previous_message_id' => $first->id],
    'messages' => $updatedMessages,
]);

$second->diagnostics?->cache_miss_reason?->type;                      // 'system_changed'
$second->diagnostics?->cache_miss_reason?->cache_missed_input_tokens; // 41850
```

`diagnostics` is `null` when nothing diverged. When it's set but `cache_miss_reason` is `null`, the comparison was still running when the response was built; check the next turn. The reason `type` is one of `model_changed`, `system_changed`, `tools_changed`, `messages_changed`, `previous_message_not_found`, or `unavailable`, and the four `*_changed` types include a `cache_missed_input_tokens` estimate of how much cacheable prefix was lost.

When streaming, `diagnostics` arrives on the `message_start` event's message object. Every response carries the field, `null` when you didn't ask for diagnostics. Requests that still send the old `cache-diagnosis-2026-04-07` header keep working.

## Fast mode

Claude Opus 5.5 can answer faster at a higher price with `speed` set to `fast`. It's generally available on that model, with no beta header:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 1024,
    'speed' => 'fast',
    'messages' => $messages,
]);

$response->usage->speed; // 'fast'
```

On Claude Opus 5 and Opus 4.8 it's a research preview behind the `fast-mode-2026-02-01` header, on the Claude API only. Opus 4.6 accepts the header but answers at standard speed, which `usage->speed` reports as `'standard'`. Fast requests have their own rate limits, read through [`meta()`](../reference/meta-information.md#fast-mode-limits); when they run out you get a 429 or a 529 rather than a slower answer, so retry without `speed` if you'd rather wait less. Fast mode isn't available with Message Batches or Priority Tier.

---

For the full list of parameters, content block types, and the latest API changes, see the [Messages API reference](https://platform.claude.com/docs/en/api/messages/create) on the Anthropic docs.
