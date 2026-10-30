---
title: Models
weight: 8
---

The Models resource lets you list all available Claude models and get details about a specific one. This is useful for building model selectors, checking availability, or programmatically discovering new models.

## Listing models

```php
$response = $client->models()->list();

foreach ($response->data as $model) {
    $model->id;             // 'claude-sonnet-5-5'
    $model->type;           // 'model'
    $model->createdAt;      // '2026-09-28T00:00:00Z'
    $model->displayName;    // 'Claude Sonnet 5.5'
    $model->maxInputTokens; // 1000000
    $model->maxTokens;      // 128000
}
```

Each item in `$response->data` is a full `RetrieveResponse`, so everything covered below (capabilities, token limits, lifecycle) is available on list items too. By default the list holds active and deprecated models; retired ones are left out.

The response includes pagination metadata:

```php
$response->firstId; // 'claude-haiku-5-5'
$response->lastId;  // 'claude-haiku-4-5-20251001'
$response->hasMore; // true
```

`firstId` and `lastId` are `null` on an empty page.

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
$response = $client->models()->retrieve('claude-sonnet-5-5');

$response->id;             // 'claude-sonnet-5-5'
$response->type;           // 'model'
$response->createdAt;      // '2026-09-28T00:00:00Z'
$response->displayName;    // 'Claude Sonnet 5.5'
$response->maxInputTokens; // 1000000
$response->maxTokens;      // 128000
```

This returns a `RetrieveResponse` with the same fields as each item in the list.

## Lifecycle and retirement

Each model says where it is in its life, which family it belongs to, and when it stops working:

```php
$model = $client->models()->retrieve('claude-sonnet-4-5-20250929');

$model->lifecycle;    // 'active', 'deprecated' or 'retired'
$model->line;         // 'sonnet'
$model->deprecatedAt; // when it was deprecated, or null
$model->retiresAt;    // when requests to it start failing, or null
```

`lifecycle` is the signal to act on: a `deprecated` model still answers, and its `retiresAt` tells you how long you have to move off it. A deprecated model whose `retiresAt` is already in the past is overdue to retire, so treat it as retired. `line` groups the generations of one family (`opus`, `sonnet`, `haiku` and so on), and new lines may appear. All four fields are `null` when the API doesn't send them.

A check you could run on a schedule, against the models your app uses:

```php
foreach (['claude-sonnet-5-5', 'claude-haiku-4-5'] as $id) {
    $model = $client->models()->retrieve($id);

    if (in_array($model->lifecycle, ['deprecated', 'retired'], true)) {
        // warn: $model->id retires at $model->retiresAt
    }
}
```

## Token limits

Every model reports two token ceilings:

- `maxInputTokens` is the size of the context window. It caps how much you can send in: system prompt, prior turns, tool definitions, and the new user message all count against it.
- `maxTokens` is the largest value you can set for the `max_tokens` parameter on a request to that model. It caps the output.

Use these to gate requests before hitting the API. If you're building a chat UI that counts tokens client-side, read `maxInputTokens` to know when to truncate history. Both are `null` when the API doesn't report a limit, so give them a fallback.

## Capabilities

The `capabilities` object tells you which features a model supports. Each capability is a small DTO with a `supported` boolean, and some group related flags underneath. `capabilities` itself can be `null`, so reach into it with `?->`.

```php
$model = $client->models()->retrieve('claude-sonnet-5-5');

$model->capabilities?->batch->supported;              // true
$model->capabilities?->citations->supported;          // true
$model->capabilities?->codeExecution->supported;      // true
$model->capabilities?->imageInput->supported;         // true
$model->capabilities?->pdfInput->supported;           // true
$model->capabilities?->structuredOutputs->supported;  // true
```

### Thinking

`thinking` reports whether the model supports extended thinking and which thinking types you can pass on a request:

```php
$model->capabilities?->thinking->supported;                       // true
$model->capabilities?->thinking->types->adaptive->supported;      // true
$model->capabilities?->thinking->types->enabled->supported;       // false
$model->capabilities?->thinking->types->disabled?->supported;     // false
$model->capabilities?->thinking->types->betweenTools?->supported; // true
```

`disabled` and `betweenTools` are `null` when the API doesn't list them. [Thinking](./thinking.md#turning-thinking-off) explains which models take which type.

### Effort levels

`effort` exposes which `output_config.effort` values the model accepts:

```php
$effort = $model->capabilities?->effort;

$effort?->supported;         // true
$effort?->low->supported;    // true
$effort?->medium->supported;
$effort?->high->supported;
$effort?->xhigh?->supported; // null when the API doesn't list it
$effort?->max->supported;
```

### Server tools

`serverTools` reports which server tools the model can run:

```php
$model->capabilities?->serverTools?->supported;                // true
$model->capabilities?->serverTools?->codeExecution->supported; // true
$model->capabilities?->serverTools?->webSearch->supported;     // true
```

### Context management

`contextManagement` covers the named strategies the model supports (clearing prior thinking, clearing tool uses, compacting history). Anthropic ships new strategy versions over time, each with a date-suffixed key like `clear_thinking_20251015`, so the DTO exposes them as a map instead of fixed properties. That way any new version the API adds shows up without a package update:

```php
$cm = $model->capabilities?->contextManagement;

$cm?->supported; // true if any strategy is supported

// Iterate every strategy the server announced
foreach ($cm?->strategies ?? [] as $name => $strategy) {
    $name;                 // 'clear_thinking_20251015'
    $strategy->supported;  // true
}

// Or check a specific strategy by its versioned key
($cm?->strategies['clear_thinking_20251015'] ?? null)?->supported;
($cm?->strategies['compact_20260112'] ?? null)?->supported;
```

Keys match the raw JSON exactly, so anything documented on the [Anthropic context management reference](https://platform.claude.com/docs/en/build-with-claude/context-management) can be looked up directly. A strategy the API sends as `null` is left out of `strategies`.

### Beta fields

Two fields come only with the matching beta header: `allowedFallbackModels` with `server-side-fallback-2026-07-01`, and the `compaction` capability with `compact-2026-09-04`. `list()` takes the headers as `betas`; for `retrieve()`, set the header on the client with `withHttpHeader('anthropic-beta', ...)`:

```php
$models = $client->models()->list(['betas' => ['server-side-fallback-2026-07-01', 'compact-2026-09-04']])->data;

foreach ($models as $model) {
    $model->allowedFallbackModels;                            // the models a refusal on this one may fall back to
    $model->capabilities?->compaction?->supported;            // compaction support
    $model->capabilities?->compaction?->summarize->supported; // on-demand compaction (`'compaction' => ['type' => 'summarize']`)
}
```

Each is `null` without its header.

### Building a feature check

A common use case: deciding which model to send a request to based on what it supports.

```php
$models = $client->models()->list()->data;

$pdfCapable = array_values(array_filter(
    $models,
    fn ($model) => $model->capabilities?->pdfInput->supported ?? false,
));
```

---

For available model IDs and capabilities, see the [Models API reference](https://platform.claude.com/docs/en/api/models/list), the [Models overview](https://platform.claude.com/docs/en/about-claude/models/overview) and the [model deprecations page](https://platform.claude.com/docs/en/about-claude/model-deprecations) on the Anthropic docs.
