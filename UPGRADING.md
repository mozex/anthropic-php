# Upgrading

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
