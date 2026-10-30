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

## Practical use

A common pattern is to check the token count before sending a long conversation, and trim older messages if you're close to the model's context limit:

```php
$count = $client->messages()->countTokens([
    'model' => 'claude-sonnet-5-5',
    'messages' => $conversationHistory,
]);

if ($count->inputTokens > 180000) {
    // Remove the oldest messages to stay within limits
    array_splice($conversationHistory, 1, 2);
}

$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'messages' => $conversationHistory,
]);
```

---

For the full request specification, see the [Count Tokens API reference](https://platform.claude.com/docs/en/api/messages/count_tokens) on the Anthropic docs.
