---
title: Completions
weight: 10
---

> **Deprecated.** The API now rejects Text Completions requests on current models: a request to `/v1/complete` returns a 400 saying the endpoint has been deprecated and pointing to `/v1/messages`. Use the [Messages API](./messages.md) instead. It supports every current model, multi-turn conversations, tool use, thinking, and the rest of the API's features.

The Completions resource stays in the package for code that still references it. It's marked `@deprecated`, so IDEs and `phpstan/phpstan-deprecation-rules` flag every call to `completions()`. The examples below show its request and response shapes; they use `claude-2.1`, a retired model, and don't run against today's API.

## Creating a completion

```php
$response = $client->completions()->create([
    'model' => 'claude-2.1',
    'prompt' => '\n\nHuman: Hello, Claude\n\nAssistant:',
    'max_tokens_to_sample' => 100,
    'temperature' => 0,
]);

$response->type;       // 'completion'
$response->id;         // 'compl_01EKm5HZ9y6khqaSZjsX44fS'
$response->completion; // ' Hello! Nice to meet you.'
$response->stop_reason; // 'stop_sequence'
$response->model;      // 'claude-2.1'
$response->stop;       // '\n\nHuman:'
$response->log_id;     // 'compl_01EKm5HZ9y6khqaSZjsX44fS'

$response->toArray(); // ['id' => 'compl_01EKm5HZ9y6khqaSZjsX44fS', ...]
```

Note the prompt format: the Text Completions API uses a specific `\n\nHuman:` / `\n\nAssistant:` turn structure rather than a messages array.

## Streamed completions

```php
$stream = $client->completions()->createStreamed([
    'model' => 'claude-2.1',
    'prompt' => 'Hi',
    'max_tokens_to_sample' => 70,
]);

foreach ($stream as $response) {
    echo $response->completion;
}
// 'I' ' am' ' very' ' excited' ...
```

Each iteration gives you the next chunk of the completion text.

## Moving to Messages

A completion prompt maps to a single user message, and `max_tokens_to_sample` becomes `max_tokens`. Leave `temperature` out: current models return a 400 for it.

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 100,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, Claude'],
    ],
]);

foreach ($response->content as $block) {
    if ($block->type === 'text') {
        echo $block->text; // 'Hello! How can I help you today?'
    }
}
```

---

For the full specification, see the [Text Completions API reference](https://platform.claude.com/docs/en/api/completions/create) on the Anthropic docs.
