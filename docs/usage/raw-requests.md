---
title: Raw Requests
weight: 11
---

`request()` sends a request to any API path, for an endpoint the client has no method for yet. It goes through the same transport as every other call: your key, the configured headers, retries, middleware and per-request options all apply, and an error status throws the same typed exception (see [Error Handling](../reference/error-handling.md)).

```php
$response = $client->request('GET', 'skills', ['limit' => 20]);

$response->status;            // 200
$response->data;              // ['data' => [...], 'next_page' => 'next_page']
$response->meta()->requestId; // 'req_018EeWyXxfu5pfWkrYcMdjWG'
```

## Paths and parameters

The path is relative to the API root, and the form the API reference prints works too: `/v1/skills` and `skills` go to the same place.

For `GET` and `DELETE`, the parameters go in the query string, with a list sent as `key[]=value` once per value. For `POST` and `PUT`, they go in a JSON body:

```php
$response = $client->request('POST', 'messages/count_tokens', [
    'model' => 'claude-opus-5-5',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, world'],
    ],
]);

$response->data['input_tokens']; // 10
```

A `betas` parameter becomes the `anthropic-beta` header, as on every other method. The method can be written in any case; `PATCH` and other methods the API doesn't use throw an `InvalidArgumentException` before anything is sent. Raw requests send JSON only, so uploads still go through `files()->upload()`, and they don't stream: a `'stream' => true` request waits for the whole answer and returns the event text, so use `messages()->createStreamed()` for that. A full URL in place of the path throws. A raw request doesn't follow `next_page` either; pass it back as `page` for the next page.

## The response

`data` is the decoded body when the API answers with a JSON object or array (`application/json`), and the body's text otherwise, such as an empty string for a `204` or the lines of a JSONL answer. `status` is the HTTP status. `meta()` reads the request ID and the rate-limit headers like every other response (see [Meta Information](../reference/meta-information.md)).

## Testing

`ClientFake` answers raw requests from its queue. Queue a `RawResponse::fake()` with the body you want back, and assert on `Raw::class`:

```php
use Anthropic\Resources\Raw;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\Testing\ClientFake;

$client = new ClientFake([
    RawResponse::fake(['data' => [], 'next_page' => null]),
]);

$client->request('GET', 'skills', ['limit' => 20]);

$client->assertSent(Raw::class, function (string $method, string $httpMethod, string $path, array $parameters): bool {
    return $httpMethod === 'GET' && $path === 'skills' && $parameters['limit'] === 20;
});
```

The fake records the path as you passed it, and throws on a method the client would reject. `RawResponse::fake()` takes the whole body rather than overrides, the meta information second as on every other fake, and the status third: `RawResponse::fake('', status: 204)` fakes an answer without a body.

For the endpoints themselves, see the [Anthropic API reference](https://platform.claude.com/docs/en/api/overview).
