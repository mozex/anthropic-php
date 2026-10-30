# Upgrading

## To 1.12.0

### ErrorException Gains context()

**Likelihood Of Impact: Low**

`ErrorException` has a new public `context()` method, which returns the request ID, the status, the error type, the `retry-after` seconds and the error code for a log entry. Laravel's exception handler, Sentry and Flare call it on their own. A subclass of your own that already defines `context()` without the `array` return type, or with required parameters, no longer loads: rename it, or declare it as `public function context(): array`.

### ClientFake Gains Connection Views

**Likelihood Of Impact: Low**

`ClientFake` has new `onConnection()`, `assertSentOn()` and `assertNotSentOn()` methods and a protected `$connection` property, so a framework can hand out one view of a fake per connection, and `TestRequest` has `connection()` and a fifth constructor argument for it. `Factory` has a new `withConfig()`. A subclass of your own that already declares one of them with another signature no longer loads.

## To 1.11.0

### Error Statuses Throw Subclasses Of ErrorException

**Likelihood Of Impact: Low**

Each error status now throws its own subclass of `ErrorException`, such as `NotFoundException` for a 404 or `OverloadedException` for a 529, and an error in the middle of a stream throws the subclass of its error type. Every `catch (ErrorException $e)` and `$e instanceof ErrorException` keeps working. Code that compares the exact class, such as `get_class($e) === ErrorException::class`, no longer matches: use `instanceof`. Error trackers that group by class name file these under the new names. A `rate_limit_error` in the middle of a stream is now a `RateLimitException`, so a `catch (RateLimitException $e)` sees it too, with status 200 and no `Retry-After`.

Connection failures throw `ConnectionException`, and timeouts `TimeoutException` (a `ConnectionException`), both subclasses of `TransporterException`, so `catch (TransporterException $e)` still catches them.

### The Contracts Gain withOptions()

**Likelihood Of Impact: Low**

For implementers of the contracts: `ClientContract` and the `@internal` `TransporterContract` have a new `withOptions(array $options)` method, which returns a client (or transporter) whose requests carry extra headers, query parameters, betas, an API version, a workspace or a user profile. A class of your own that implements either interface needs it:

```php
// ClientContract
public function withOptions(array $options): ClientContract;

// TransporterContract
public function withOptions(array $options): self;
```

`ClientFake` implements it, sharing its queue with the fake it returns.

### The Contracts Gain request() And iterate()

**Likelihood Of Impact: Low**

For implementers of the contracts: `ClientContract` has a new `request()` method, which sends a request to any API path, and `ModelsContract`, `BatchesContract` and `FilesContract` have a new `iterate()`, which walks every page of `list()`. The `@internal` `TransporterContract` has a new `requestRaw()`. A class of your own that implements one of them needs the method:

```php
// ClientContract
public function request(string $method, string $path, array $parameters = []): RawResponse;

// ModelsContract, BatchesContract, FilesContract
public function iterate(array $parameters = []): Generator;

// TransporterContract
public function requestRaw(Payload $payload): Response;
```

`ClientFake` and its test resources implement them. No class is final, so a subclass of `Client`, `ClientFake`, the Models, Batches or Files resources or their test resources that already declares a `request()` or `iterate()` method of its own needs these signatures, or a new name for its method.

### A List In The Query Is Sent As key[]=

**Likelihood Of Impact: Low**

A list in a query parameter, such as the Models list's `lifecycle`, now goes out as `lifecycle[]=retired` once per value. Before, it went out as `lifecycle[0]=retired`, which the API accepts and ignores, so the filter did nothing. If your code passed such a list, the results now follow it.

## To 1.10.0

### Every Error Status Throws ErrorException

**Likelihood Of Impact: Medium**

A streamed request that fails with a 4xx or 5xx status, such as a 529 overload, used to throw `TransporterException`. It now throws `ErrorException` (or `RateLimitException` for a 429) with the API's error type, the same as a request that isn't streamed. A response with a status of 400 or above whose body isn't the API's JSON, such as a proxy's HTML error page or a plain-text 503, used to throw `UnserializableResponse` or a `TypeError`. It now throws `ErrorException` with that status, `getErrorType()` returning `null`, and the first line of the page's text as the message (a 429 is a `RateLimitException` with the type `rate_limit_error`).

If you catch `TransporterException` around a stream to retry overloads, or `UnserializableResponse` to detect a gateway error, catch `ErrorException` there as well:

```php
try {
    foreach ($client->messages()->createStreamed($parameters) as $event) {
        // ...
    }
} catch (ErrorException $e) {
    if ($e->getErrorType() === 'overloaded_error') {
        // retry later
    }
}
```

`files()->download()` throws the same way, where it used to return an error page as the file's contents.

### toArray() Returns Every Field The API Sent

**Likelihood Of Impact: Low**

`toArray()` and array access on every response now return every key the API sent, in the API's order: fields this release doesn't type yet, and fields sent as `null`, such as `diagnostics`, `stop_details` and `container`. A response's `content` can be sent back to the API as it is. A stream event's `toArray()` lists the event's own keys first, so its key order changed.

Array access checks whether the key exists, so `isset($response['diagnostics'])` is now `true` when the API sent `null`. Read such fields through the typed property or with `??`:

```php
$response->diagnostics;             // null
$response['diagnostics'] ?? 'none'; // 'none'
```

A few other arrays gain keys: an errored batch result's `toArray()['error']` has `request_id` beside its type and message, and `meta()->toArray()` has `anthropic-organization-id` (sent on every response), `anthropic-workspace-id`, `retry-after` and the `anthropic-fast-*` headers when the response carries them. Those headers stay in `custom` too, where earlier releases put them.

A `message_delta` event's `delta.stop_details` comes out of `toArray()` with both `category` and `explanation`, as on a response that isn't streamed.

### An Empty Tool Input Is An ArrayObject In toArray()

**Likelihood Of Impact: Low**

The API rejects a replayed tool call whose `input` is `[]`: it wants a JSON object. So an empty `input` on a `tool_use`, `server_tool_use` or `mcp_tool_use` block now comes out of `toArray()` as an `ArrayObject`, which encodes as `{}`. The typed `$block->input` is still `[]`.

Code that reads `toArray()['content'][$i]['input']` may notice: `is_array()` and `empty()` return `false` for it, and array functions such as `array_keys()` throw. `count()`, `foreach` and `??` work as before, and `from()` and `fake()` accept a response's own `toArray()` output. Two `toArray()` calls on the same response no longer compare equal with `===`.

### Nullable Models And List Fields

**Likelihood Of Impact: Low**

The API documents these fields as nullable, and a missing value used to throw a `TypeError`: `maxInputTokens`, `maxTokens` and `capabilities` on a model, and `firstId` and `lastId` on the models and batches lists. They're nullable now, so static analysis may flag code that assumed a value.

`CreateStreamedResponseContentBlockStart::$content` is `array|string|null`: an on-demand compaction block arrives whole with string content. A block start like that threw a `TypeError` before.

### Hand-Written Fake Streams Need Blank Lines Between Events

**Likelihood Of Impact: Low**

Streams are now read per the SSE spec, so `data:` lines that aren't separated by a blank line are joined into one event. A fake stream written by hand needs a blank line after each event, as the helper in the testing docs writes.

### Text Completions Is Deprecated

**Likelihood Of Impact: Low**

The API now rejects `/v1/complete` requests on current models, so `completions()`, `CompletionsContract` and the `Completions` resource are marked `@deprecated`. IDEs and `phpstan/phpstan-deprecation-rules` flag every call to them. They keep working for as long as the API serves your model. Use the Messages API instead.

For implementers of the contracts: a class that implements `ClientContract` inherits the deprecation on its own `completions()` method, so PHPStan flags the calls to it, not the class.

### Base URI Trailing Slash

**Likelihood Of Impact: None (Backwards Compatible)**

A base URI ending in `/`, such as `https://api.anthropic.com/v1/`, used to produce paths like `/v1//messages`. The trailing slash is dropped now.

## To 1.2.0

### ErrorException Is No Longer Final

**Likelihood Of Impact: Low**

`ErrorException` has been changed from `final class` to `class` to allow `RateLimitException` to extend it. Existing `catch (ErrorException $e)` blocks and `instanceof` checks are unaffected. If your code uses `get_class($e) === ErrorException::class` (exact class match), be aware that `RateLimitException` will not match — use `$e instanceof ErrorException` instead.

### ErrorException Now Accepts ResponseInterface

**Likelihood Of Impact: None (Backwards Compatible)**

The `ErrorException` constructor now accepts either a `Psr\Http\Message\ResponseInterface` or an `int` as its second argument. Passing an `int` still works as before but is considered deprecated. The full PSR-7 response is now available via the `$response` property when a `ResponseInterface` is provided:

```php
try {
    $result = $client->messages()->create([...]);
} catch (\Anthropic\Exceptions\ErrorException $e) {
    $e->getStatusCode();                         // works as before
    $e->response?->getHeaderLine('Retry-After'); // new: access full response headers
}
```

### New RateLimitException for HTTP 429 Responses

**Likelihood Of Impact: Low**

HTTP 429 responses now throw `Anthropic\Exceptions\RateLimitException`. This exception **extends** `ErrorException`, so existing `catch (ErrorException $e)` blocks will continue to catch rate limit errors without any changes. The API's original error message is preserved in the exception. If you want to handle rate limits specifically, you can catch it before `ErrorException`:

```php
try {
    $result = $client->messages()->create([...]);
} catch (\Anthropic\Exceptions\RateLimitException $e) {
    // specific rate limit handling
    $retryAfter = $e->response->getHeaderLine('Retry-After');
} catch (\Anthropic\Exceptions\ErrorException $e) {
    // other API errors
}
```

### UnserializableResponse Now Accepts ResponseInterface

**Likelihood Of Impact: None (Backwards Compatible)**

The `UnserializableResponse` exception now accepts an optional `Psr\Http\Message\ResponseInterface` as its second constructor argument. The previous constructor signature still works without changes. When provided, the response is available via the `$response` property for debugging:

```php
try {
    $result = $client->messages()->create([...]);
} catch (\Anthropic\Exceptions\UnserializableResponse $e) {
    $e->response?->getBody(); // inspect the raw response that failed to parse
}
```

### TransporterContract Has a New Method

**Likelihood Of Impact: Low**

The `Anthropic\Contracts\TransporterContract` interface (marked `@internal`) now includes an `addHeader()` method. If you have a custom implementation of this interface, you will need to implement it:

```php
public function addHeader(string $name, string $value): self;
```

### Fakeable Override Strategy

**Likelihood Of Impact: Low**

The `fake()` method on response classes now uses `array_replace_recursive` for merging overrides instead of the previous custom recursive logic. In most cases the behavior is identical. If you need to fully replace an array value rather than merge into it, use the new `OverrideStrategy::Replace` parameter:

```php
use Anthropic\Testing\Enums\OverrideStrategy;

CreateResponse::fake(
    override: ['content' => [['type' => 'text', 'text' => 'Hello']]],
    strategy: OverrideStrategy::Replace,
);
```
