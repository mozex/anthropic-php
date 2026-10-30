---
title: Thinking
weight: 4
---

Extended thinking lets Claude reason before answering. This is useful for complex tasks like math, logic, analysis, and code generation where step-by-step reasoning produces better results.

There are two modes: **adaptive thinking**, where the model decides how much to think, and **budget-based thinking** for older models. Every Claude 5 model uses adaptive thinking when you don't set `thinking` at all. Which models let you turn thinking off is covered in [Turning thinking off](#turning-thinking-off).

## Adaptive thinking

Adaptive thinking is the default on every Claude 5 model, and you opt into it on Opus 4.8, 4.7 and 4.6 and Sonnet 4.6. The model decides when and how deeply to think based on the complexity of your request. Simple questions might get no thinking at all; hard problems get extensive reasoning.

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'adaptive',
        'display' => 'summarized',
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Explain why the sum of two even numbers is always even.'],
    ],
]);
```

The response content may include `thinking` blocks before the `text` block:

```php
foreach ($response->content as $block) {
    if ($block->type === 'thinking') {
        echo $block->thinking;  // 'Let me analyze this step by step...'
        echo $block->signature; // 'WaUjzkypQ2mUEVM36O2TxuC06KN8xyfbJwyem...'
    }

    if ($block->type === 'redacted_thinking') {
        echo $block->data; // 'EmwKAhgBEgy3va3pzix/LafPsn4a'
    }

    if ($block->type === 'text') {
        echo $block->text; // 'Based on my analysis...'
    }
}
```

Three content block types can appear:

- **`thinking`**: Claude's reasoning, with a cryptographic `signature` for verification
- **`redacted_thinking`**: Internal reasoning that Anthropic has redacted for safety, returned as opaque `data`
- **`text`**: The final answer

## Display options

Control how thinking content appears in responses with the `display` option. The default depends on the model: `omitted` on every Claude 5 model, Opus 4.8 and Opus 4.7, and `summarized` on Opus 4.6, Sonnet 4.6 and earlier.

**Summarized**: Thinking blocks contain a summary of Claude's reasoning.

```php
'thinking' => [
    'type' => 'adaptive',
    'display' => 'summarized',
]
```

**Omitted**: The `thinking` field is empty, but the `signature` is still present. This is useful when you don't need to show the thinking to users but want to preserve it for multi-turn conversations.

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'adaptive',
        'display' => 'omitted',
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What is 27 * 453?'],
    ],
]);

$response->content[0]->type;      // 'thinking'
$response->content[0]->thinking;  // '' (empty)
$response->content[0]->signature; // 'EosnCkYICxIMMb3LzNrMu...' (always present)
$response->content[1]->text;      // 'The answer is 12,231.'
```

**Updates** (beta): Short progress notes before tool calls, on Fable 5.1, Mythos 5.1, Fable 5, Opus 5.5 and Sonnet 5.5:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 16000,
    'betas' => ['thinking-display-updates-2026-08-18'],
    'thinking' => [
        'type' => 'adaptive',
        'display' => 'updates',
    ],
    'tools' => $tools,
    'messages' => $messages,
]);

$response->content[1]->thinking; // 'Confirmed the retry path never refreshes the expired token. Editing auth.py to add the refresh call.'
```

Reasoning blocks stay empty, as with `omitted`; only the progress blocks right before a tool call carry text. An empty `thinking` block means there's nothing to show.

## Effort levels

Use `output_config.effort` to guide how much the model thinks. Lower effort means faster responses and fewer tokens, but potentially less thorough reasoning.

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'adaptive',
    ],
    'output_config' => [
        'effort' => 'medium',
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What is the capital of France?'],
    ],
]);
```

| Effort | Behavior | Best for |
|--------|----------|----------|
| `max` | No constraints on thinking depth. | Hard math, complex analysis, multi-step reasoning |
| `xhigh` | Between `high` and `max`. | Tasks where `high` isn't enough |
| `high` | Thorough thinking. | Most tasks |
| `medium` | Moderate thinking. May skip for simple queries. | Balanced speed and quality |
| `low` | Minimal thinking. Skips entirely for trivial queries. | Simple questions, high-throughput use cases |

The default depends on the model: `high` on Sonnet 5.5 and Fable 5.1, `medium` on Opus 5.5 and Haiku 5.5. Not every model accepts every level; the [Models API](./models.md#effort-levels) tells you which ones a model takes. With the `mid-conversation-output-config-2026-07-01` beta you can also change effort partway through a conversation, as the [Messages page](./messages.md#mid-conversation-system-messages) shows.

## Turning thinking off

The models differ here:

| Model | `'type' => 'disabled'` | `'type' => 'enabled'` with `budget_tokens` |
|-------|------------------------|--------------------------------------------|
| Fable 5.1, Mythos 5.1, Fable 5, Mythos 5, Opus 5.5 | 400 error | 400 error |
| Sonnet 5.5 | 400 error: send `between_tools` | 400 error |
| Opus 5, Haiku 5.5 | Only at effort `high` or below | 400 error |
| Sonnet 5, Opus 4.8, Opus 4.7 | Allowed | 400 error |
| Opus 4.6, Sonnet 4.6 | Allowed | Deprecated, still works |
| Opus 4.5, Sonnet 4.5, Haiku 4.5 | Allowed | Allowed |

Only Claude Sonnet 5.5 accepts `between_tools`, in place of `disabled`. It works at effort `high` or below and takes no other field, `display` included. Progress updates still come back as `thinking` blocks with text:

```php
'thinking' => ['type' => 'between_tools']
```

## Budget-based thinking

The models in the last row of the table above (Opus 4.5, Sonnet 4.5, Haiku 4.5) take a fixed token budget for thinking, and they reject `adaptive`. The mode is deprecated on Opus 4.6 and Sonnet 4.6, and Claude 4.7 and later models return a 400 for it; use adaptive thinking with the `effort` parameter there.

```php
$response = $client->messages()->create([
    'model' => 'claude-haiku-4-5',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'enabled',
        'budget_tokens' => 10000,
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'Are there an infinite number of prime numbers such that n mod 4 == 3?'],
    ],
]);
```

The `budget_tokens` value must be less than `max_tokens`. This is the maximum number of tokens Claude can use for thinking; it may use fewer.

## Thinking token usage

Thinking is billed as output tokens, even when the text is summarized or omitted. To see how much of the bill was reasoning, read `outputTokensDetails` on the usage object:

```php
$response->usage->outputTokens;                         // 200
$response->usage->outputTokensDetails?->thinkingTokens; // 150
```

When streaming, this breakdown arrives only on the final `message_delta` event.

## Thinking with tool use

When using adaptive thinking with [tool use](./tool-use.md), interleaved thinking is enabled automatically. This means Claude can reason between tool calls, making it effective for agentic workflows where the model needs to plan its next step based on a tool's result.

No extra configuration is needed. Just include both `thinking` and `tools` in your request.

## Streaming with thinking

Thinking works with streaming. See [Streaming](./streaming.md#streaming-with-thinking) for the full event sequence, including `thinking_delta` and `signature_delta` events.

## Multi-turn with thinking

When building multi-turn conversations with thinking enabled, include the thinking blocks and their signatures in the conversation history. This lets Claude verify the integrity of its previous reasoning.

Pass the full content array (thinking blocks included) as the assistant's message:

```php
// First turn
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 16000,
    'thinking' => ['type' => 'adaptive'],
    'messages' => [
        ['role' => 'user', 'content' => 'Solve x^2 + 5x + 6 = 0'],
    ],
]);

// Second turn: include the full response content (with thinking blocks)
$followUp = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 16000,
    'thinking' => ['type' => 'adaptive'],
    'messages' => [
        ['role' => 'user', 'content' => 'Solve x^2 + 5x + 6 = 0'],
        ['role' => 'assistant', 'content' => $response->toArray()['content']],
        ['role' => 'user', 'content' => 'Now verify by substitution.'],
    ],
]);
```

This is why the `signature` field exists even when `display` is set to `'omitted'`: it's needed for multi-turn continuity. Send every block back unchanged and in order, thinking blocks with an empty `thinking` included; a block sent back with an empty `signature` fails. Keep `system` and `tools` fixed for the session and only append to `messages`.

### Replayed thinking that the API drops

On Claude Fable 5.1, Mythos 5.1, Opus 5.5, Sonnet 5.5 and Haiku 5.5, a thinking block is tied to the model and the conversation that produced it. When a replayed block doesn't match (sent to another model, or after the history before it changed), the API can drop it, and the model answers without that reasoning. The `thinking-binding-controls-2026-08-01` beta lets you choose what happens, and reports what it did in `input_transformations`:

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-5-5',
    'max_tokens' => 16000,
    'betas' => ['thinking-binding-controls-2026-08-01'],
    'thinking' => [
        'type' => 'adaptive',
        'block_binding' => ['prefix_mismatch_behavior' => 'drop_block'],
    ],
    'messages' => $messages,
]);

foreach ($response->input_transformations ?? [] as $transformation) {
    $transformation['type'];   // 'thinking_dropped'
    $transformation['path'];   // 'messages.3.content.0'
    $transformation['reason']; // 'model_binding_mismatch'
}
```

`drop_block` drops each failing block and the thinking blocks after it, and the request goes on; dropped blocks aren't billed. `error` rejects the request with a 400 naming the first failing block. Without `block_binding`, the outcome depends on the model and the account, and a `thinking_mismatch_allowed` entry means a failing block was used and billed. With the header set, every response from a thinking model carries `input_transformations`, an empty array when nothing changed. A `model_binding_mismatch` after switching models is expected, not a bug in your code. The API may add other entry types, so skip the ones you don't recognize.

---

For thinking token limits, pricing, and advanced patterns like interleaved thinking with tool use, see the [Extended thinking guide](https://platform.claude.com/docs/en/build-with-claude/extended-thinking), the [Adaptive thinking guide](https://platform.claude.com/docs/en/build-with-claude/adaptive-thinking) and the [Preserved thinking guide](https://platform.claude.com/docs/en/build-with-claude/preserved-thinking) on the Anthropic docs.
