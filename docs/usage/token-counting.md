---
title: Token Counting
weight: 7
---

The token counting endpoint tells you how many input tokens a message would use without actually creating it. This is useful for cost estimation, staying within context limits, or deciding whether to trim a conversation before sending.

## Counting tokens

Use `countTokens()` with the same parameters you'd pass to `create()`:

```php
$response = $client->messages()->countTokens([
    'model' => 'claude-sonnet-4-6',
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
    'model' => 'claude-sonnet-4-6',
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
    'model' => 'claude-sonnet-4-6',
    'system' => 'You are a helpful PHP expert.',
    'messages' => [
        ['role' => 'user', 'content' => 'Explain generators.'],
    ],
]);
```

## Practical use

A common pattern is to check the token count before sending a long conversation, and trim older messages if you're close to the model's context limit:

```php
$count = $client->messages()->countTokens([
    'model' => 'claude-sonnet-4-6',
    'messages' => $conversationHistory,
]);

if ($count->inputTokens > 180000) {
    // Remove the oldest messages to stay within limits
    array_splice($conversationHistory, 1, 2);
}

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => $conversationHistory,
]);
```

---

For the full request specification, see the [Count Tokens API reference](https://platform.claude.com/docs/en/api/messages/count_tokens) on the Anthropic docs.
