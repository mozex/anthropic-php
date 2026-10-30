---
title: Token Counting
weight: 7
---

The token counting endpoint tells you how many input tokens a message would use without actually creating it. This is useful for cost estimation, staying within context limits, or deciding whether to trim a conversation before sending.

## Counting tokens

Use `countTokens()` with the same parameters you'd pass to `create()`:

```php
$response = $client->messages()->countTokens([
    'model' => 'claude-sonnet-5-5',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);

$response->inputTokens; // 2095
```

The response returns a `CountTokensResponse` with a single `inputTokens` field.

## Counting with tools

If your request includes [tools](./tool-use.md), include them in the count. Tool definitions contribute to the token count:

```php
$response = $client->messages()->countTokens([
    'model' => 'claude-sonnet-5-5',
    'tools' => [
        [
            'name' => 'get_weather',
            'description' => 'Get the current weather in a given location',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string'],
                ],
                'required' => ['location'],
            ],
        ],
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'What is the weather in Paris?'],
    ],
]);

$response->inputTokens; // includes tokens from the tool definition
```

## Counting with system messages

System messages also contribute to the count:

```php
$response = $client->messages()->countTokens([
    'model' => 'claude-sonnet-5-5',
    'system' => 'You are a helpful PHP expert.',
    'messages' => [
        ['role' => 'user', 'content' => 'Explain generators.'],
    ],
]);
```

## Counting with context editing

With the context editing beta, the count reflects the edits the API would apply, and the response also tells you how many tokens the request had before them:

```php
$response = $client->messages()->countTokens([
    'model' => 'claude-sonnet-5-5',
    'betas' => ['context-management-2025-06-27'],
    'context_management' => [
        'edits' => [
            ['type' => 'clear_tool_uses_20250919'],
        ],
    ],
    'messages' => $messages,
]);

$response->inputTokens;       // 25000
$response->contextManagement; // ['original_input_tokens' => 70000]
```

`contextManagement` is `null` on a count without the beta. Token counting ignores the `compaction` parameter of on-demand compaction.

## Checking a request fits

`fits()` counts a request and compares its input tokens with a limit. Pass the same parameters you'll give `create()`: only what `count_tokens` takes is sent (`model`, `messages`, `system`, `tools`, `tool_choice`, `thinking`, `betas` and the rest), so `max_tokens`, `stream` and `temperature` don't get in the way:

```php
$parameters = [
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'messages' => $conversationHistory,
];

if (! $client->messages()->fits($parameters, 150000)) {
    // too long: trim it, summarize it, or ask the user to start over
}
```

The limit counts input tokens only, so leave room for the answer yourself. A model's own input limit is `maxInputTokens` on its [model details](./models.md).

`count_tokens` can't count everything `create()` takes: server tools other than the advisor, the MCP connector, and image or document blocks with a `url` or `file` source get a `BadRequestException`, which these helpers throw as it comes. Count with base64 sources, or leave those parts out of the count.

## Trimming a conversation to fit

`trimToFit()` drops the oldest turns of `messages` until the request fits, and returns the parameters ready for `create()`:

```php
$parameters = $client->messages()->trimToFit($parameters, 150000);

$response = $client->messages()->create($parameters);
```

It only cuts before a user turn that answers no tool call, so every `tool_use` keeps its `tool_result` and the conversation still starts with the user. The system prompt, the tools and every other parameter stay as they were, but a `role: system` message among the dropped turns goes with them, so keep lasting instructions in `system`. A request that already fits comes back unchanged after one count; otherwise it counts a few more times, halving its way to a cut that fits. When even the last turn it can start from is over the limit, it throws `Anthropic\Exceptions\InvalidArgumentException` with the request's count from that turn, system prompt and tools included.

Thinking blocks need one more setting. Each kept turn's thinking was produced while the dropped turns were still there, and Claude 5.1 models reject such blocks with a 400 unless you tell the API to drop them, with the `thinking-binding-controls-2026-08-01` beta:

```php
$parameters = $client->messages()->trimToFit([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'betas' => ['thinking-binding-controls-2026-08-01'],
    'thinking' => ['type' => 'adaptive', 'block_binding' => ['prefix_mismatch_behavior' => 'drop_block']],
    'messages' => $conversationHistory,
], 150000);
```

Once a conversation is over the limit, each cut moves every turn, which costs its prompt-cache hits, so trim to well under the limit rather than just under it. `count_tokens` is free, but it has its own rate limit: call these once per request rather than in a loop. For long-running conversations, [compaction](./messages.md) keeps the history on the server's side instead.

---

For the full request specification, see the [Count Tokens API reference](https://platform.claude.com/docs/en/api/messages/count_tokens) on the Anthropic docs.
