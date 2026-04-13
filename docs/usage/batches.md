---
title: Batches
weight: 9
---

Message Batches let you send large volumes of message requests and process them asynchronously. Instead of making individual API calls, you submit a batch of requests that Anthropic processes in the background, at 50% of the cost, with results available within 24 hours.

## Creating a batch

Submit multiple message requests at once. Each request gets a `custom_id` that you use later to match results:

```php
$response = $client->batches()->create([
    'requests' => [
        [
            'custom_id' => 'request-1',
            'params' => [
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => 'What is the capital of France?'],
                ],
            ],
        ],
        [
            'custom_id' => 'request-2',
            'params' => [
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => 'What is the capital of Germany?'],
                ],
            ],
        ],
    ],
]);

$response->id;                        // 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x'
$response->type;                      // 'message_batch'
$response->processingStatus;          // 'in_progress'
$response->requestCounts->processing; // 2
$response->requestCounts->succeeded;  // 0
$response->createdAt;                 // '2025-04-01T12:00:00Z'
$response->expiresAt;                 // '2025-04-02T12:00:00Z'
$response->endedAt;                   // null
$response->cancelInitiatedAt;         // null
$response->archivedAt;                // null
$response->resultsUrl;                // null
```

The `params` object inside each request takes the same parameters you'd pass to `$client->messages()->create()`. You can use any feature that the [Messages API](./messages.md) supports: [tool use](./tool-use.md), [thinking](./thinking.md), system messages, and so on.

## Checking batch status

Poll for completion using `retrieve()`:

```php
$response = $client->batches()->retrieve('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

$response->processingStatus;              // 'ended'
$response->requestCounts->processing;    // 0
$response->requestCounts->succeeded;     // 95
$response->requestCounts->errored;       // 3
$response->requestCounts->canceled;      // 0
$response->requestCounts->expired;       // 2
$response->resultsUrl; // 'https://api.anthropic.com/v1/messages/batches/msgbatch_.../results'
```

Batch processing statuses:

| Status | Meaning |
|--------|---------|
| `in_progress` | Batch is being processed |
| `canceling` | Cancellation requested, finishing in-progress requests |
| `ended` | All requests have completed (check `requestCounts` for breakdown) |

A canceled batch ends up with status `ended`, not a separate `canceled` status. The `requestCounts` breakdown tells you how many individual requests succeeded, errored, were canceled, or expired.

## Getting results

Once a batch has ended, stream the results with `results()`. Each result contains the original `custom_id` and the outcome:

```php
$response = $client->batches()->results('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

foreach ($response as $individual) {
    $individual->customId;     // 'request-1'
    $individual->result->type; // 'succeeded', 'errored', 'canceled', or 'expired'

    if ($individual->result->type === 'succeeded') {
        $individual->result->message->id;               // 'msg_014VwiXbi91y3JMjcpyGBHX2'
        $individual->result->message->content[0]->text; // 'The capital of France is Paris.'
    }

    if ($individual->result->type === 'errored') {
        $individual->result->error->type;    // 'invalid_request_error'
        $individual->result->error->message; // 'max_tokens: Field required'
    }
}

$response->meta(); // [rate limit headers](../reference/meta-information.md)
```

Results are streamed as JSONL, so you can process them one at a time without loading everything into memory.

For succeeded requests, `$individual->result->message` is a full [message response](./messages.md) with the same structure you'd get from `$client->messages()->create()`.

## Listing batches

List your batches with cursor-based pagination:

```php
$response = $client->batches()->list(['limit' => 10]);

foreach ($response->data as $batch) {
    $batch->id;               // 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x'
    $batch->processingStatus; // 'ended'
}

$response->hasMore; // true
$response->firstId; // 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x'
$response->lastId;  // 'msgbatch_07V2nm5PqB3bP8szLgTmn1EG'
```

## Canceling a batch

Cancel a batch that's still processing. Requests that are already in-progress will finish, but no new requests will start:

```php
$response = $client->batches()->cancel('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

$response->processingStatus; // 'canceling'
```

## Deleting a batch

Delete a batch after it has completed. Only batches with `ended` status can be deleted:

```php
$response = $client->batches()->delete('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

$response->id;   // 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x'
$response->type; // 'message_batch_deleted'
```

## Typical workflow

Here's the full lifecycle for a batch job:

```php
// 1. Create the batch
$batch = $client->batches()->create(['requests' => $requests]);

// 2. Poll until complete
do {
    sleep(30);
    $batch = $client->batches()->retrieve($batch->id);
} while ($batch->processingStatus !== 'ended');

// 3. Process results
$results = $client->batches()->results($batch->id);

foreach ($results as $individual) {
    if ($individual->result->type === 'succeeded') {
        processResult($individual->customId, $individual->result->message);
    }
}

// 4. Clean up
$client->batches()->delete($batch->id);
```
