<?php

use Anthropic\Enums\Transporter\ContentType;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;

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
