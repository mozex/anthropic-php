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
                'model' => 'claude-sonnet-5-5',
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => 'What is the capital of France?'],
                ],
            ],
        ],
        [
            'custom_id' => 'request-2',
            'params' => [
                'model' => 'claude-sonnet-5-5',
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

A canceled batch ends up with status `ended`, not a separate `canceled` status. The `requestCounts` breakdown tells you how many individual requests succeeded, errored, were canceled, or expired. `isEnded()` is true only for `ended`; a batch that's still `canceling` hasn't ended.

### Waiting for a batch to end

`wait()` retrieves the batch every 60 seconds until it has ended, then returns it:

```php
$batch = $client->batches()->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');

$batch->isEnded(); // true
```

`interval` sets the seconds between polls. With a `timeout` in seconds, the last poll comes at the timeout and `wait()` returns the batch as it was then, so check `isEnded()`:

```php
$batch = $client->batches()->wait($id, interval: 30, timeout: 600);

if (! $batch->isEnded()) {
    // still processing after ten minutes: check again later
}
```

The timeout counts the time slept between polls, not the time each request takes. `wait()` blocks the PHP process the whole time, and most batches take minutes to an hour (up to 24), so in a web request or a queued job, poll on a schedule instead. Pass `sleep` to replace PHP's `sleep()`, for example with one that returns at once in a test:

```php
$batch = $client->batches()->wait($id, sleep: fn (int $seconds) => null);
```

Under `ClientFake`, each poll is a recorded `retrieve()` answered from the queue, and nothing sleeps: queue one `BatchResponse::fake(['processing_status' => 'in_progress'])` and then one that has ended.

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
        $individual->result->error->type;      // 'invalid_request_error'
        $individual->result->error->message;   // 'max_tokens: Field required'
        $individual->result->error->requestId; // the failed request's ID, or null
    }
}

$response->meta(); // [rate limit headers](../reference/meta-information.md)
```

Results are streamed as JSONL, so you can process them one at a time without loading everything into memory.

`succeeded()`, `errored()`, `canceled()` and `expired()` yield only the results of that type:

```php
foreach ($client->batches()->results($batch->id)->errored() as $individual) {
    $individual->customId;              // 'request-2'
    $individual->result->error->message; // 'max_tokens: Field required'
}
```

The results are read once, as they arrive. A second loop over the same response, through a filter or not, continues where the first one stopped, so fetch the results again with `results()` to read another type.

For succeeded requests, `$individual->result->message` is a full [message response](./messages.md) with the same structure you'd get from `$client->messages()->create()`.

For errored requests, `error` is the API error that request got. Its `requestId` is the one to quote to support, though some errored results come back without one. Results are read under the API version of the request that created the batch.

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

`iterate()` walks every page for you, following `lastId`, and yields one batch at a time. Nothing is requested until you loop:

```php
foreach ($client->batches()->iterate(['limit' => 100]) as $batch) {
    if ($batch->processingStatus === 'in_progress') {
        // ...
    }
}
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
$batch = $client->batches()->wait($batch->id, interval: 30);

// 3. Process results
foreach ($client->batches()->results($batch->id)->succeeded() as $individual) {
    processResult($individual->customId, $individual->result->message);
}

// 4. Clean up
$client->batches()->delete($batch->id);
```

---

For batch limits, pricing, and the full API specification, see the [Batch processing guide](https://platform.claude.com/docs/en/build-with-claude/batch-processing) on the Anthropic docs.
