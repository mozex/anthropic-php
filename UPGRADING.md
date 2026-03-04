# Upgrading

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
