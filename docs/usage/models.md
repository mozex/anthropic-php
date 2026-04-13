---
title: Models
weight: 8
---

The Models resource lets you list all available Claude models and get details about a specific one. This is useful for building model selectors, checking availability, or programmatically discovering new models.

## Listing models

```php
$response = $client->models()->list();

foreach ($response->data as $model) {
    $model->id;          // 'claude-sonnet-4-6'
    $model->type;        // 'model'
    $model->createdAt;   // '2025-05-14T00:00:00Z'
    $model->displayName; // 'Claude Sonnet 4.6'
}
```

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

$response->id;          // 'claude-sonnet-4-6'
$response->type;        // 'model'
$response->createdAt;   // '2025-05-14T00:00:00Z'
$response->displayName; // 'Claude Sonnet 4.6'
```

This returns a `RetrieveResponse` with the same fields as each item in the list.
