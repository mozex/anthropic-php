---
title: Thinking
weight: 4
---

Extended thinking lets Claude show its reasoning process before answering. This is useful for complex tasks like math, logic, analysis, and code generation where step-by-step reasoning produces better results.

There are two modes: **adaptive thinking** for newer models, and **budget-based thinking** for older ones. You can also explicitly disable thinking with `'type' => 'disabled'`.

## Adaptive thinking

Adaptive thinking is the recommended approach for Claude Opus 4.6 and Sonnet 4.6. The model decides when and how deeply to think based on the complexity of your request. Simple questions might get no thinking at all; hard problems get extensive reasoning.

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'adaptive',
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

- **`thinking`**: Claude's visible reasoning, with a cryptographic `signature` for verification
- **`redacted_thinking`**: Internal reasoning that Anthropic has redacted for safety, returned as opaque `data`
- **`text`**: The final answer

## Display options

Control how thinking content appears in responses with the `display` option:

**Summarized** (default): Thinking blocks contain a summary of Claude's reasoning.

```php
'thinking' => [
    'type' => 'adaptive',
    'display' => 'summarized',
]
```

**Omitted**: The `thinking` field is empty, but the `signature` is still present. This is useful when you don't need to show the thinking to users but want to preserve it for multi-turn conversations.

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
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

## Effort levels

Use `output_config.effort` to guide how much the model thinks. Lower effort means faster responses and fewer tokens, but potentially less thorough reasoning.

```php
$response = $client->messages()->create([
    'model' => 'claude-opus-4-6',
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
| `max` | No constraints on thinking depth. Opus 4.6 and Sonnet 4.6. | Hard math, complex analysis, multi-step reasoning |
| `high` | Default. Thorough thinking. | Most tasks |
| `medium` | Moderate thinking. May skip for simple queries. | Balanced speed and quality |
| `low` | Minimal thinking. Skips entirely for trivial queries. | Simple questions, high-throughput use cases |

## Budget-based thinking

For older models (Claude Sonnet 3.7, Opus 4.5, Sonnet 4.5), use `budget_tokens` to set a fixed token budget for thinking. This mode is deprecated on Opus 4.6 and Sonnet 4.6; use adaptive thinking with the `effort` parameter instead.

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-5',
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

## Thinking with tool use

When using adaptive thinking with [tool use](./tool-use.md), interleaved thinking is enabled automatically. This means Claude can reason between tool calls, making it effective for agentic workflows where the model needs to plan its next step based on a tool's result.

No extra configuration is needed. Just include both `thinking` and `tools` in your request.

## Streaming with thinking

Thinking works with streaming. See [Streaming](./streaming.md) for the full event sequence, including `thinking_delta` and `signature_delta` events.

## Multi-turn with thinking

When building multi-turn conversations with thinking enabled, include the thinking blocks and their signatures in the conversation history. This lets Claude verify the integrity of its previous reasoning.

Pass the full content array (thinking blocks included) as the assistant's message:

```php
// First turn
$response = $client->messages()->create([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 16000,
    'thinking' => ['type' => 'adaptive'],
    'messages' => [
        ['role' => 'user', 'content' => 'Solve x^2 + 5x + 6 = 0'],
    ],
]);

// Second turn: include the full response content (with thinking blocks)
$followUp = $client->messages()->create([
    'model' => 'claude-opus-4-6',
    'max_tokens' => 16000,
    'thinking' => ['type' => 'adaptive'],
    'messages' => [
        ['role' => 'user', 'content' => 'Solve x^2 + 5x + 6 = 0'],
        ['role' => 'assistant', 'content' => $response->toArray()['content']],
        ['role' => 'user', 'content' => 'Now verify by substitution.'],
    ],
]);
```

This is why the `signature` field exists even when `display` is set to `'omitted'`: it's needed for multi-turn continuity.

---

For thinking token limits, pricing, and advanced patterns like interleaved thinking with tool use, see the [Extended thinking guide](https://platform.claude.com/docs/en/build-with-claude/extended-thinking) and [Adaptive thinking guide](https://platform.claude.com/docs/en/build-with-claude/adaptive-thinking) on the Anthropic docs.
