---
title: Models
weight: 8
---

The Models resource lets you list all available Claude models and get details about a specific one. This is useful for building model selectors, checking availability, or programmatically discovering new models.

## Listing models

```php
$response = $client->models()->list();

foreach ($response->data as $model) {
    $model->id;             // 'claude-sonnet-4-6'
    $model->type;           // 'model'
    $model->createdAt;      // '2025-05-14T00:00:00Z'
    $model->displayName;    // 'Claude Sonnet 4.6'
    $model->maxInputTokens; // 200000
    $model->maxTokens;      // 64000
}
```

Each item in `$response->data` is a full `RetrieveResponse`, so everything covered below (capabilities, token limits) is available on list items too.

The response includes pagination metadata:

```php
$response->firstId; // 'claude-sonnet-4-6'
$response->lastId;  // 'claude-haiku-4-5'
$response->hasMore; // true
```

## Pagination

The Models API uses cursor-based pagination. Use `limit` to control page size (defaults to 20, max 1000) and `after_id` to fetch the next page:

```php
// First page
$page1 = $client->models()->list(['limit' => 5]);

// Next page (if more exist)
if ($page1->hasMore) {
    $page2 = $client->models()->list([
        'limit' => 5,
        'after_id' => $page1->lastId,
    ]);
}
```

You can also paginate backward using `before_id` with `firstId`:

```php
$previousPage = $client->models()->list([
    'limit' => 5,
    'before_id' => $page2->firstId,
]);
```

To fetch all models:

```php
$allModels = [];

$response = $client->models()->list(['limit' => 20]);
$allModels = array_merge($allModels, $response->data);

while ($response->hasMore) {
    $response = $client->models()->list([
        'limit' => 20,
        'after_id' => $response->lastId,
    ]);
    $allModels = array_merge($allModels, $response->data);
}
```

## Retrieving a single model

Get details about a specific model by its ID:

```php
$response = $client->models()->retrieve('claude-sonnet-4-6');

$response->id;             // 'claude-sonnet-4-6'
$response->type;           // 'model'
$response->createdAt;      // '2025-05-14T00:00:00Z'
$response->displayName;    // 'Claude Sonnet 4.6'
$response->maxInputTokens; // 200000
$response->maxTokens;      // 64000
```

This returns a `RetrieveResponse` with the same fields as each item in the list.

## Token limits

Every model reports two token ceilings:

- `maxInputTokens` is the size of the context window. It caps how much you can send in: system prompt, prior turns, tool definitions, and the new user message all count against it.
- `maxTokens` is the largest value you can set for the `max_tokens` parameter on a request to that model. It caps the output.

Use these to gate requests before hitting the API. If you're building a chat UI that counts tokens client-side, read `maxInputTokens` to know when to truncate history.

## Capabilities

The `capabilities` object tells you which features a model supports. Each capability is a small DTO with a `supported` boolean, and some group related flags underneath.

```php
$model = $client->models()->retrieve('claude-sonnet-4-6');

$model->capabilities->batch->supported;              // true
$model->capabilities->citations->supported;          // true
$model->capabilities->codeExecution->supported;      // true
$model->capabilities->imageInput->supported;         // true
$model->capabilities->pdfInput->supported;           // true
$model->capabilities->structuredOutputs->supported;  // true
```

### Thinking

`thinking` reports whether the model supports extended thinking and which thinking types you can pass on a request:

```php
$model->capabilities->thinking->supported;                   // true
$model->capabilities->thinking->types->adaptive->supported;  // true
$model->capabilities->thinking->types->enabled->supported;   // true
```

### Effort levels

`effort` exposes which `reasoning_effort` values the model accepts:

```php
$effort = $model->capabilities->effort;

$effort->supported;       // true
$effort->low->supported;  // true
$effort->medium->supported;
$effort->high->supported;
$effort->max->supported;
```

### Context management

`contextManagement` covers the named strategies the model supports (clearing prior thinking, clearing tool uses, compacting history). Anthropic ships new strategy versions over time, each with a date-suffixed key like `clear_thinking_20251015`, so the DTO exposes them as a map instead of fixed properties. That way any new version the API adds shows up without a package update:

```php
$cm = $model->capabilities->contextManagement;

$cm->supported; // true if any strategy is supported

// Iterate every strategy the server announced
foreach ($cm->strategies as $name => $strategy) {
    $name;                 // 'clear_thinking_20251015'
    $strategy->supported;  // true
}

// Or check a specific strategy by its versioned key
$cm->strategies['clear_thinking_20251015']?->supported;
$cm->strategies['compact_20260112']?->supported;
```

Keys match the raw JSON exactly, so anything documented on the [Anthropic context management reference](https://platform.claude.com/docs/en/build-with-claude/context-management) can be looked up directly.

### Building a feature check

A common use case: deciding which model to send a request to based on what it supports.

```php
$models = $client->models()->list()->data;

$pdfCapable = array_values(array_filter(
    $models,
    fn ($model) => $model->capabilities->pdfInput->supported,
));
```

---

For available model IDs and capabilities, see the [Models API reference](https://platform.claude.com/docs/en/api/models/list) and [Models overview](https://platform.claude.com/docs/en/about-claude/models/overview) on the Anthropic docs.
