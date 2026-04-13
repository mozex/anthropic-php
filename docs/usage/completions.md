---
title: Completions
weight: 10
---

> **Legacy API.** The Text Completions API is Anthropic's older generation interface. For all new projects, use the [Messages API](./messages.md) instead. It supports newer models, multi-turn conversations, tool use, thinking, and all other current features.

The Completions resource is included for backward compatibility with projects that still use it.

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
