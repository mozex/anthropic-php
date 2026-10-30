---
title: Testing
weight: 4
---

The SDK includes a fake client implementation that lets you test your code without making real API calls. You set up fake responses, run your code, and then assert that the right requests were sent.

## Setting up the fake client

Replace the real `Anthropic\Client` with `Anthropic\Testing\ClientFake` in your tests. Pass an array of fake responses that will be returned in order:

```php
use Anthropic\Testing\ClientFake;
use Anthropic\Responses\Messages\CreateResponse;

$client = new ClientFake([
    CreateResponse::fake([
        'content' => [
            ['type' => 'text', 'text' => 'Hello! How can I help?'],
        ],
    ]),
]);

$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

$response->content[0]->text; // 'Hello! How can I help?'
```

The `fake()` method on response classes creates a response object with sensible defaults. Pass an array to override only the fields your test cares about. Overrides are merged recursively by default, so you can override a single nested field without replacing the entire parent array. A nested item whose `type` differs from the default's, such as a `tool_use` block in place of the default `text` block, replaces it instead of merging into it.

## Available fake responses

Every response class has a `fake()` method:

| Response class | Resource |
|---------------|----------|
| `Messages\CreateResponse::fake()` | Messages create |
| `Messages\CountTokensResponse::fake()` | Token counting |
| `Messages\CreateStreamedResponse::fake()` | Messages streaming |
| `Completions\CreateResponse::fake()` | Completions create (deprecated) |
| `Completions\CreateStreamedResponse::fake()` | Completions streaming (deprecated) |
| `Models\ListResponse::fake()` | Models list |
| `Models\RetrieveResponse::fake()` | Models retrieve |
| `Batches\BatchResponse::fake()` | Batch create/retrieve/cancel |
| `Batches\BatchListResponse::fake()` | Batch list |
| `Batches\BatchResultResponse::fake()` | Batch results |
| `Batches\DeletedBatchResponse::fake()` | Batch delete |
| `Files\FileResponse::fake()` | Files upload/retrieve metadata |
| `Files\FileListResponse::fake()` | Files list |
| `Files\DeletedFileResponse::fake()` | Files delete |

## Overriding response fields

Only override what matters for your test. Everything else gets default values:

```php
use Anthropic\Responses\Messages\CreateResponse;

$client = new ClientFake([
    CreateResponse::fake([
        'stop_reason' => 'max_tokens',
        'usage' => ['output_tokens' => 1024],
    ]),
]);

$response = $client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Write a long story.'],
    ],
]);

expect($response->stop_reason)->toBe('max_tokens')
    ->and($response->usage->outputTokens)->toBe(1024);
```

## Fake streamed responses

For streaming, pass a file resource containing the SSE event data:

```php
use Anthropic\Testing\ClientFake;
use Anthropic\Responses\Messages\CreateStreamedResponse;

$client = new ClientFake([
    CreateStreamedResponse::fake(fopen('tests/fixtures/stream.txt', 'r')),
]);

$stream = $client->messages()->createStreamed([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

expect($stream->getIterator()->current())
    ->type->toBe('message_start');
```

You can also call `fake()` with no arguments. It uses a built-in default fixture file:

```php
$client = new ClientFake([
    CreateStreamedResponse::fake(),
]);
```

### Building a stream from text chunks

For tests that need predictable streamed text (without a fixture file), you can build a fake stream in memory. This helper takes an array of text parts and returns a resource with the full SSE event sequence:

```php
function fakeStream(array $parts)
{
    $events = [];

    $events[] = [
        'type' => 'message_start',
        'message' => [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-5-5',
            'content' => [],
            'stop_reason' => null,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 1],
        ],
    ];

    foreach ($parts as $part) {
        $events[] = [
            'type' => 'content_block_delta',
            'index' => 0,
            'delta' => ['type' => 'text_delta', 'text' => $part],
        ];
    }

    $events[] = [
        'type' => 'message_delta',
        'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null],
        'usage' => ['output_tokens' => 12],
    ];

    $events[] = ['type' => 'message_stop'];

    $body = implode("\n\n", array_map(
        fn (array $event): string => "event: {$event['type']}\ndata: " . json_encode($event),
        $events,
    ));

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $body);
    rewind($handle);

    return $handle;
}

// Use it in a test
$client = new ClientFake([
    CreateStreamedResponse::fake(fakeStream(['Hello', ', ', 'world!'])),
]);
```

The stream emits `message_start`, three `content_block_delta` events with the text parts, `message_delta`, and `message_stop`. This is enough to test code that reads incremental text without needing an external fixture file.

## Testing errors

To test error handling, pass an exception as a fake response. It will be thrown when the matching request is made:

```php
use Anthropic\Testing\ClientFake;
use Anthropic\Exceptions\ErrorException;
use GuzzleHttp\Psr7\Response;

$client = new ClientFake([
    new ErrorException([
        'message' => 'Overloaded',
        'type' => 'overloaded_error',
    ], new Response(529)),
]);

// This will throw the ErrorException
$client->messages()->create([
    'model' => 'claude-sonnet-5-5',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello'],
    ],
]);
```

Like the client, pass any PSR-7 response as the second argument, such as Guzzle's. The status comes from it, and headers you add, such as a `Retry-After` on a `RateLimitException`, reach the code under test through `$e->response`:

```php
use Anthropic\Exceptions\RateLimitException;

new RateLimitException(
    ['message' => 'Rate limited', 'type' => 'rate_limit_error'],
    new Response(429, ['retry-after' => '30']),
);
```

An `int` status code still works in place of the response, but it's deprecated, and `$e->response` is `null` then.

The client throws a subclass for each status, such as `OverloadedException` for a 529, and you can queue those the same way: `new OverloadedException([...], new Response(529))`. Code that catches `ErrorException` catches them all.

## Assertions

After running your code, use assertions to verify which requests were sent.

### assertSent

Check that a request was made to a specific resource:

```php
use Anthropic\Resources\Messages;

// Assert any request was sent to the Messages resource
$client->assertSent(Messages::class);

// Assert with a callback for specific parameters
$client->assertSent(Messages::class, function (string $method, array $parameters): bool {
    return $method === 'create'
        && $parameters['model'] === 'claude-sonnet-5-5';
});

// Assert a specific number of requests
$client->assertSent(Messages::class, 2);
```

### assertNotSent

Check that no request was made to a resource:

```php
use Anthropic\Resources\Batches;
use Anthropic\Resources\Messages;

$client->assertNotSent(Batches::class);

// With a callback: assert no request matched this condition
$client->assertNotSent(Messages::class, function (string $method, array $parameters): bool {
    return $parameters['model'] === 'claude-opus-5-5';
});

// With a number: assert the resource wasn't called exactly that many times
$client->assertNotSent(Messages::class, 2);
```

### assertNothingSent

Check that no requests were sent at all:

```php
$client->assertNothingSent();
```

### Resource-level assertions

You can also assert directly on a resource:

```php
$client->messages()->assertSent(function (string $method, array $parameters): bool {
    return $method === 'create'
        && $parameters['max_tokens'] === 1024;
});

$client->batches()->assertNotSent();
```

### assertSentWithOptions

`withOptions()` on the fake returns a fake that shares its queue and recorded requests, so the code under test can call it freely. Each request it sends records the options, and `assertSentWithOptions()` checks them:

```php
use Anthropic\Resources\Messages;

$client = new ClientFake([CreateResponse::fake()]);

// the code under test
$client->withOptions(['workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ'])->messages()->create([...]);

$client->assertSent(Messages::class, 1);
$client->assertSentWithOptions(Messages::class, ['workspace_id' => 'wrkspc_01JwQvzr7rXLA5AGx3HKfFUJ']);
```

The expected options can be a subset: a request carrying more options still matches, and header names match whatever their spelling. On a resource, `$client->messages()->assertSentWithOptions([...])` does the same.

## Adding responses dynamically

If you need to add responses after creating the fake client (for example, in a loop or conditional logic):

```php
$client = new ClientFake();

$client->addResponses([
    CreateResponse::fake(['content' => [['type' => 'text', 'text' => 'First response']]]),
    CreateResponse::fake(['content' => [['type' => 'text', 'text' => 'Second response']]]),
]);
```

## Multiple sequential requests

Fake responses are consumed in order. The first request gets the first response, the second request gets the second, and so on:

```php
$client = new ClientFake([
    CreateResponse::fake(['content' => [['type' => 'text', 'text' => 'First']]]),
    CreateResponse::fake(['content' => [['type' => 'text', 'text' => 'Second']]]),
]);

$first = $client->messages()->create([...]); // Returns "First"
$second = $client->messages()->create([...]); // Returns "Second"
```

If you make more requests than you have fake responses, the client will throw an error.
