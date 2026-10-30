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

A fake given no responses at all answers every call with its method's default fake, such as `CreateResponse::fake()` for `messages()->create()`, so a test that doesn't care about the answer needs none. Once you queue a response, or call `preventStrayRequests()`, a call with nothing to answer it throws instead, naming the call:

```php
$client = new ClientFake;
$client->messages()->create([...]); // a CreateResponse::fake()

$client = (new ClientFake)->preventStrayRequests();
$client->messages()->create([...]); // throws: No fake responses left. Messages::create() has none, ...
```

## Answering by resource, method or closure

`respondTo()` answers every matching call, ahead of the queue and as often as it comes. Match a resource's class, a resource and method pair, or a closure that takes the `TestRequest`:

```php
use Anthropic\Resources\Messages;
use Anthropic\Resources\Models;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\Requests\TestRequest;

$client = (new ClientFake)
    ->respondTo([Models::class, 'retrieve'], RetrieveResponse::fake())
    ->respondTo(
        fn (TestRequest $request): bool => ($request->args()[0]['model'] ?? null) === 'claude-haiku-4-5',
        fn (TestRequest $request) => CreateResponse::fake(['model' => 'claude-haiku-4-5']),
    );
```

The response can be a closure that takes the `TestRequest` and returns one. A stream needs that, since it can be read only once. The first matching responder answers; a call none matches takes the next queued response. A response that doesn't fit the method, such as a `ListResponse` queued for `messages()->create()`, throws, naming both.

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
| `Raw\RawResponse::fake()` | `request()` (see [Raw Requests](../usage/raw-requests.md)) |

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

### Building a stream from a response or from events

To stream a message without a fixture file, pass `fake()` the `CreateResponse` it should stream. The fake sends the events the API would for it: `message_start`, each block opened and filled by its deltas (text, thinking and its signature, a tool call's input as JSON), `message_delta` with the stop reason, then `message_stop`:

```php
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Testing\Blocks;

$client = new ClientFake([
    CreateStreamedResponse::fake(CreateResponse::fake([
        'content' => [Blocks::text('Hello, world!')],
    ])),
]);
```

For exact control, pass the events themselves, each an array with its `type`:

```php
CreateStreamedResponse::fake([
    ['type' => 'message_start', 'message' => [...]],
    ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hello']],
    ['type' => 'message_stop'],
]);
```

A stream fake carries the same rate-limit headers and request ID as the other fakes, so `meta()` works on it. Pass your own as the second argument: `CreateStreamedResponse::fake($response, ['request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'])`.

## Content blocks

`Blocks` builds the content blocks a fake's `content` holds, in the shapes the API sends, with the IDs from the API docs' examples:

```php
use Anthropic\Testing\Blocks;

CreateResponse::fake([
    'content' => [
        Blocks::thinking('Let me check the weather.'),
        Blocks::toolUse('get_weather', ['location' => 'San Francisco, CA']),
    ],
    'stop_reason' => 'tool_use',
]);
```

There are `text()` (with optional citations), `toolUse()`, `thinking()`, `serverToolUse()`, `webSearchToolResult()` and `webSearchResult()`. Each takes its fields as arguments, with defaults for the rest.

## Faking batch results

`BatchResultResponse::fake()` takes a list of result lines, as the results endpoint sends them:

```php
use Anthropic\Responses\Batches\BatchResultResponse;

$client = new ClientFake([
    BatchResultResponse::fake([
        ['custom_id' => 'my-first-request', 'result' => ['type' => 'succeeded', 'message' => CreateResponse::fake()->toArray()]],
        ['custom_id' => 'my-second-request', 'result' => ['type' => 'canceled']],
    ]),
]);
```

Like a stream fake, it takes headers for `meta()` as a second argument.

## Record and replay

`Recorder` runs a test against the real API once and saves what came back to a cassette file, then replays the file on every later run, with no HTTP call and no key. Add it as the client's last middleware, so any middleware before it runs on replays too:

```php
use Anthropic\Testing\Recorder;

$client = Anthropic::factory()
    ->withApiKey(getenv('ANTHROPIC_API_KEY') ?: 'replaying')
    ->withMiddleware(Recorder::cassette(__DIR__.'/cassettes/summary.json', record: getenv('ANTHROPIC_RECORD') ? true : null))
    ->withRetrySleep(fn (int $milliseconds) => null)
    ->make();
```

While the file is missing, the recorder sends each request and saves the answer. Once it exists, it answers each request from the file, in order. A request to another method or path than the one recorded throws, so a test that calls something new tells you to record again. Request bodies and queries aren't saved, so a changed prompt replays the old answer: record again with `record: true` (here, `ANTHROPIC_RECORD=1`). A key the API refuses (a 401) throws, and the cassette is left as it was. For a stream, set `withStreamHandler()` too unless your HTTP client is Guzzle or Symfony.

A cassette keeps each request's method and path, and each answer's status, body (byte for byte: JSON that PHP would write differently and binary bodies are kept as text and base64) and the headers the client reads: `content-type`, `request-id`, `retry-after` and the `anthropic-` rate-limit headers, without `anthropic-organization-id` and `anthropic-workspace-id`. It never keeps a request's headers or body, so your key and prompts stay out of it, but an answer's body is kept as sent, so look a cassette over before you commit it. A retried request is two entries, so a replay retries the same way, and waits its `retry-after` unless `withRetrySleep()` skips the wait as above. The path includes a proxy's prefix, so record and replay with the same base URL.

## Testing errors

To test error handling, queue an exception. It's thrown when the matching request is made. Every API exception has a `fake()` that builds it as the client would: its status, the error type and message from the API's errors page, a JSON body and a `request-id` header on a real PSR-7 response:

```php
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Exceptions\RateLimitException;

$client = new ClientFake([OverloadedException::fake()]);

$client->messages()->create([...]); // throws OverloadedException, status 529

RateLimitException::fake(headers: ['retry-after' => '30']);                     // getRetryAfter() is 30
RateLimitException::fake(['details' => ['error_code' => 'enforced_spend_limit_reached']]); // the spend cap
OverloadedException::fake(status: 200);                                          // an error in the middle of a stream
```

The first argument goes into the error object (`message`, `details`), `headers` onto the response, and `status` replaces the class's own. `ErrorException::fake(status: 405)` makes one for a status without a class of its own, typed `invalid_request_error` as the API types other 4xx statuses. A subclass of your own fakes as its parent, and `['request_id' => 'req_...']` sets the request ID. A failure without an answer has fakes too: `ConnectionException::fake()` and `TimeoutException::fake()` wrap a PSR-18 network exception, and `TransporterException::fake('...')` a plain PSR-18 client exception.

You can still build one by hand, with any PSR-7 response as the second argument: `new RateLimitException(['message' => 'Rate limited'], new Response(429, ['retry-after' => '30']))`. An `int` status code in place of the response is deprecated, and `$e->response` is `null` then. Code that catches `ErrorException` catches every subclass.

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

### assertSentTimes and assertSentInOrder

`assertSentTimes()` counts a resource's calls, or only those a callback accepts. `assertSentInOrder()` checks every call that was sent, in order, each as a resource's class, a resource and method pair, or a closure taking the `TestRequest`:

```php
use Anthropic\Resources\Models;

$client->assertSentTimes(Messages::class, 2);
$client->assertSentTimes(Messages::class, 1, fn (string $method, array $parameters): bool => $parameters['model'] === 'claude-haiku-4-5');

$client->assertSentInOrder([
    [Models::class, 'retrieve'],
    [Messages::class, 'create'],
]);
```

A failure lists the calls that were sent. `sent()` returns them as `TestRequest` objects, all of them, those to a resource, or those a callback accepts: `$client->sent(Messages::class)[0]->args()`.

### Resource-level assertions

You can also assert directly on a resource:

```php
$client->messages()->assertSent(function (string $method, array $parameters): bool {
    return $method === 'create'
        && $parameters['max_tokens'] === 1024;
});

$client->messages()->assertSentTimes(2);

$client->batches()->assertNotSent();
```

### Pest expectations

Under Pest, a fake also works with `expect()`:

```php
expect($client)
    ->toHaveSent(Messages::class)
    ->toHaveSentTimes(Messages::class, 2)
    ->toHaveSentInOrder([Models::class, Messages::class])
    ->not->toHaveSent(Batches::class);
```

`toHaveSentOn()` and `toHaveSentNothing()` follow `assertSentOn()` and `assertNothingSent()`. They're registered the first time a fake is made, and only when Pest is installed.

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

If you make more requests than you have fake responses, the next call throws, naming it: `No fake responses left. Messages::create() was the next call: ...`

## Fakes per connection

A framework that hands out one client per connection (the Laravel package's `Anthropic::connection()`) gives each a view of one fake: `onConnection('support')` shares the queue and the recorded requests, and records its own under that name. `assertSentOn()` checks which connection a call used:

```php
$fake = new ClientFake([CreateResponse::fake()]);

$fake->onConnection('support')->messages()->create([...]);

$fake->assertSentOn('support', Messages::class);
$fake->assertSentOn('support', Messages::class, 1); // exactly once
$fake->assertNotSentOn('billing', Messages::class);
```

Calls through the fake itself carry no connection name. A subclass can give them one by setting the protected `$connection` property, as the Laravel package's fake does for its default connection.

## Faking pages for iterate()

`iterate()` on the fake calls the fake's own `list()` for each page, so every page is a queued response and a recorded `list` request. Queue a page whose cursor says there's more, then the last one:

```php
use Anthropic\Resources\Models;
use Anthropic\Responses\Models\ListResponse;

$client = new ClientFake([
    ListResponse::fake(['has_more' => true, 'last_id' => 'claude-sonnet-4-6']),
    ListResponse::fake(),
]);

$models = iterator_to_array($client->models()->iterate());

$client->assertSent(Models::class, 2);
$client->assertSent(Models::class, fn (string $method, array $parameters = []): bool => ($parameters['after_id'] ?? null) === 'claude-sonnet-4-6');
```

The list fakes end with `has_more` false, so a single `ListResponse::fake()` is one page. The walk takes the next page from the queue only when the loop reaches it, so a loop body that makes fake calls of its own needs their responses queued between the pages.
