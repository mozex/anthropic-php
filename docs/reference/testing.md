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
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

$response->content[0]->text; // 'Hello! How can I help?'
```

The `fake()` method on response classes creates a response object with sensible defaults. Pass an array to override only the fields your test cares about. Overrides are merged recursively by default, so you can override a single nested field without replacing the entire parent array.

## Available fake responses

Every response class has a `fake()` method:

| Response class | Resource |
|---------------|----------|
| `Messages\CreateResponse::fake()` | Messages create |
| `Messages\CountTokensResponse::fake()` | Token counting |
| `Messages\CreateStreamedResponse::fake()` | Messages streaming |
| `Completions\CreateResponse::fake()` | Completions create |
| `Completions\CreateStreamedResponse::fake()` | Completions streaming |
| `Models\ListResponse::fake()` | Models list |
| `Models\RetrieveResponse::fake()` | Models retrieve |
| `Batches\BatchResponse::fake()` | Batch create/retrieve/cancel |
| `Batches\BatchListResponse::fake()` | Batch list |
| `Batches\BatchResultResponse::fake()` | Batch results |
| `Batches\DeletedBatchResponse::fake()` | Batch delete |

## Overriding response fields

Only override what matters for your test. Everything else gets default values:

```php
use Anthropic\Responses\Completions\CreateResponse;

$client = new ClientFake([
    CreateResponse::fake([
        'completion' => 'PHP is awesome!',
    ]),
]);

$response = $client->completions()->create([
    'model' => 'claude-2.1',
    'prompt' => '\n\nHuman: PHP is \n\nAssistant:',
    'max_tokens_to_sample' => 100,
]);

expect($response->completion)->toBe('PHP is awesome!');
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
    'model' => 'claude-sonnet-4-6',
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

## Testing errors

To test error handling, pass an exception as a fake response. It will be thrown when the matching request is made:

```php
use Anthropic\Testing\ClientFake;
use Anthropic\Exceptions\ErrorException;

$client = new ClientFake([
    new ErrorException([
        'message' => 'Overloaded',
        'type' => 'overloaded_error',
    ], 529),
]);

// This will throw the ErrorException
$client->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'messages' => [
        ['role' => 'user', 'content' => 'Hello'],
    ],
]);
```

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
        && $parameters['model'] === 'claude-sonnet-4-6';
});

// Assert a specific number of requests
$client->assertSent(Messages::class, 2);
```

### assertNotSent

Check that no request was made to a resource:

```php
use Anthropic\Resources\Completions;
use Anthropic\Resources\Messages;

$client->assertNotSent(Completions::class);

// With a callback: assert no request matched this condition
$client->assertNotSent(Messages::class, function (string $method, array $parameters): bool {
    return $parameters['model'] === 'claude-2.1';
});
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

$client->completions()->assertNotSent();
```

## Adding responses dynamically

If you need to add responses after creating the fake client (for example, in a loop or conditional logic):

```php
$client = new ClientFake();

$client->addResponses([
    CreateResponse::fake(['completion' => 'First response']),
    CreateResponse::fake(['completion' => 'Second response']),
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
