<?php

declare(strict_types=1);

use Anthropic\Enums\Transporter\ContentType;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;
use Psr\Http\Message\RequestInterface;

it('has a method', function () {
    $payload = Payload::create('models', []);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create();

    expect($payload->toRequest($baseUri, $headers, $queryParams)->getMethod())->toBe('POST');
});

it('has a uri', function () {
    $payload = Payload::list('models');

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create()
        ->withParam('foo', 'bar')
        ->withParam('baz', 'qux');

    $uri = $payload->toRequest($baseUri, $headers, $queryParams)->getUri();

    expect($uri->getHost())->toBe('api.anthropic.com')
        ->and($uri->getScheme())->toBe('https')
        ->and($uri->getPath())->toBe('/v1/models')
        ->and($uri->getQuery())->toBe('foo=bar&baz=qux');
});

test('get verb does not have a body', function () {
    $payload = Payload::list('models');

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create();

    expect($payload->toRequest($baseUri, $headers, $queryParams)->getBody()->getContents())->toBe('');
});

test('post verb has a body', function () {
    $payload = Payload::create('models', [
        'name' => 'test',
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create();

    expect($payload->toRequest($baseUri, $headers, $queryParams)->getBody()->getContents())->toBe(json_encode([
        'name' => 'test',
    ]));
});

test('betas in create params become an anthropic-beta header and are stripped from the body', function () {
    $payload = Payload::create('messages', [
        'model' => 'claude-opus-4-6',
        'betas' => ['files-api-2025-04-14', 'extended-cache-ttl-2025-04-11'],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))
        ->toBe('files-api-2025-04-14,extended-cache-ttl-2025-04-11');

    expect($request->getBody()->getContents())
        ->toBe(json_encode(['model' => 'claude-opus-4-6']));
});

test('betas in list params become a header and do not leak into the query string', function () {
    $payload = Payload::list('messages/batches', [
        'limit' => 10,
        'betas' => ['message-batches-2024-09-24'],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))->toBe('message-batches-2024-09-24');
    expect($request->getUri()->getQuery())->toBe('limit=10');
});

test('betas in retrieve params become a header', function () {
    $payload = Payload::retrieve('files', 'file_123', '', [
        'betas' => ['files-api-2025-04-14'],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14');
    expect($request->getUri()->getQuery())->toBe('');
});

test('betas in upload params become a header and are stripped from the multipart body', function () {
    $payload = Payload::upload('files', [
        'file' => 'bytes',
        'betas' => ['files-api-2025-04-14'],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14');
    expect($request->getBody()->getContents())->not->toContain('files-api-2025-04-14');
});

test('withBetas merges with betas already extracted from parameters, de-duplicating', function () {
    $payload = Payload::create('files', [
        'betas' => ['files-api-2025-04-14'],
    ])->withBetas(['files-api-2025-04-14', 'extended-cache-ttl-2025-04-11']);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))
        ->toBe('files-api-2025-04-14,extended-cache-ttl-2025-04-11');
});

test('per-request betas merge with a globally configured anthropic-beta header', function () {
    $payload = Payload::create('messages', [
        'betas' => ['files-api-2025-04-14'],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))
        ->withCustomHeader('anthropic-beta', 'interleaved-thinking-2025-05-14');
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))
        ->toBe('interleaved-thinking-2025-05-14,files-api-2025-04-14');
});

test('duplicate betas across global header and per-request are de-duplicated', function () {
    $payload = Payload::create('messages', [
        'betas' => ['files-api-2025-04-14', 'interleaved-thinking-2025-05-14'],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))
        ->withCustomHeader('anthropic-beta', 'interleaved-thinking-2025-05-14');
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))
        ->toBe('interleaved-thinking-2025-05-14,files-api-2025-04-14');
});

test('empty betas array is a no-op and the anthropic-beta header is not set', function () {
    $payload = Payload::create('messages', [
        'model' => 'claude-opus-4-6',
        'betas' => [],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->hasHeader('anthropic-beta'))->toBeFalse();
    expect($request->getBody()->getContents())
        ->toBe(json_encode(['model' => 'claude-opus-4-6']));
});

test('non-string beta entries are ignored', function () {
    $payload = Payload::create('messages', [
        'betas' => ['files-api-2025-04-14', 42, null, '', '  '],
    ]);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14');
});

test('withBetas on a payload with no user-provided betas still sets the header', function () {
    $payload = Payload::create('files', [])
        ->withBetas(['files-api-2025-04-14']);

    $baseUri = BaseUri::from('api.anthropic.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'));
    $queryParams = QueryParams::create();

    $request = $payload->toRequest($baseUri, $headers, $queryParams);

    expect($request->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14');
});

/**
 * The request a payload builds against the default base URI and a key.
 */
function payloadRequest(Payload $payload, ?QueryParams $queryParams = null): RequestInterface
{
    return $payload->toRequest(BaseUri::from('api.anthropic.com/v1'), Headers::withAuthorization(ApiKey::from('foo')), $queryParams ?? QueryParams::create());
}

test('a list parameter goes into the query as key[]= once per value', function () {
    expect(payloadRequest(Payload::list('models', ['lifecycle' => ['retired']]))->getUri()->getQuery())->toBe('lifecycle%5B%5D=retired')
        ->and(payloadRequest(Payload::list('models', ['lifecycle' => ['active', 'deprecated'], 'limit' => 2]))->getUri()->getQuery())
        ->toBe('lifecycle%5B%5D=active&lifecycle%5B%5D=deprecated&limit=2')
        // array_filter() leaves gaps in the keys
        ->and(payloadRequest(Payload::list('models', ['lifecycle' => [0 => 'active', 2 => 'retired']]))->getUri()->getQuery())
        ->toBe('lifecycle%5B%5D=active&lifecycle%5B%5D=retired');
});

test('the query keeps the keys of a map, skips nulls and empty lists, and keeps the factory query params', function () {
    // composed: no list endpoint takes a map today
    $request = payloadRequest(
        Payload::list('models', ['metadata' => ['team' => 'billing'], 'after_id' => null, 'lifecycle' => []]),
        QueryParams::create()->withParam('region', 'eu'),
    );

    expect($request->getUri()->getQuery())->toBe('region=eu&metadata%5Bteam%5D=billing');
});

test('a raw payload sends GET and DELETE parameters in the query and POST and PUT ones in a JSON body', function (string $method, string $query, string $body) {
    // composed: the Files list's parameters, sent with every method to show where they go
    $request = payloadRequest(Payload::raw($method, 'files', ['limit' => 2, 'ids' => ['file_011CNha8iCJcU1wXNR6q4V8w']]));

    expect($request->getMethod())->toBe($method)
        ->and($request->getUri()->getQuery())->toBe($query)
        ->and((string) $request->getBody())->toBe($body);
})->with([
    'GET' => ['GET', 'limit=2&ids%5B%5D=file_011CNha8iCJcU1wXNR6q4V8w', ''],
    'DELETE' => ['DELETE', 'limit=2&ids%5B%5D=file_011CNha8iCJcU1wXNR6q4V8w', ''],
    'POST' => ['POST', '', '{"limit":2,"ids":["file_011CNha8iCJcU1wXNR6q4V8w"]}'],
    'PUT' => ['PUT', '', '{"limit":2,"ids":["file_011CNha8iCJcU1wXNR6q4V8w"]}'],
]);

test('a raw path may start with a slash or /v1, as the API reference prints it', function (string $path) {
    expect(payloadRequest(Payload::raw('GET', $path))->getUri()->getPath())->toBe('/v1/models/claude-opus-5-5');
})->with(['models/claude-opus-5-5', '/models/claude-opus-5-5', '/v1/models/claude-opus-5-5', 'v1/models/claude-opus-5-5', ' /v1/models/claude-opus-5-5 ']);

test('a full URL in place of a raw path throws, and a bare v1 is the root', function () {
    expect(fn () => Payload::raw('GET', 'https://api.anthropic.com/v1/models'))->toThrow(InvalidArgumentException::class, 'is a URL')
        ->and(payloadRequest(Payload::raw('GET', '/v1'))->getUri()->getPath())->toBe('/v1/');
});

test('a raw path with a query of its own gets the parameters after it', function () {
    expect(payloadRequest(Payload::raw('GET', 'models?limit=1', ['after_id' => 'claude-opus-5-5']))->getUri()->getQuery())
        ->toBe('limit=1&after_id=claude-opus-5-5');
});

test('a raw payload takes the method in any case and rejects one the API has no use for', function () {
    expect(payloadRequest(Payload::raw('get', 'models'))->getMethod())->toBe('GET')
        ->and(fn () => Payload::raw('PATCH', 'models'))->toThrow(InvalidArgumentException::class, 'The method [PATCH]');
});

test('betas in raw params become a header', function () {
    $request = payloadRequest(Payload::raw('GET', 'skills', ['source' => 'custom', 'betas' => ['skills-2025-10-02']]));

    expect($request->getHeaderLine('anthropic-beta'))->toBe('skills-2025-10-02')
        ->and($request->getUri()->getQuery())->toBe('source=custom');
});

test('delete and cancel requests are built as before', function () {
    $delete = payloadRequest(Payload::delete('files', 'file_011CNha8iCJcU1wXNR6q4V8w'));
    $cancel = payloadRequest(Payload::cancel('messages/batches', 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x'));

    expect($delete->getMethod())->toBe('DELETE')
        ->and($delete->getUri()->getQuery())->toBe('')
        ->and((string) $delete->getBody())->toBe('')
        ->and($cancel->getMethod())->toBe('POST')
        ->and((string) $cancel->getUri())->toBe('https://api.anthropic.com/v1/messages/batches/msgbatch_04Rka1yCsMLGPnR7kfPdgR8x/cancel')
        ->and((string) $cancel->getBody())->toBe('[]');
});
