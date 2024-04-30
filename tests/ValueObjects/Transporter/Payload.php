<?php

use Anthropic\Enums\Transporter\ContentType;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;

it('has a method', function () {
    $payload = Payload::create('models', []);

    $baseUri = BaseUri::from('api.openai.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create();

    expect($payload->toRequest($baseUri, $headers, $queryParams)->getMethod())->toBe('POST');
});

it('has a uri', function () {
    $payload = Payload::list('models');

    $baseUri = BaseUri::from('api.openai.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create()
        ->withParam('foo', 'bar')
        ->withParam('baz', 'qux');

    $uri = $payload->toRequest($baseUri, $headers, $queryParams)->getUri();

    expect($uri->getHost())->toBe('api.openai.com')
        ->and($uri->getScheme())->toBe('https')
        ->and($uri->getPath())->toBe('/v1/models')
        ->and($uri->getQuery())->toBe('foo=bar&baz=qux');
});

test('get verb does not have a body', function () {
    $payload = Payload::list('models');

    $baseUri = BaseUri::from('api.openai.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create();

    expect($payload->toRequest($baseUri, $headers, $queryParams)->getBody()->getContents())->toBe('');
});

test('post verb has a body', function () {
    $payload = Payload::create('models', [
        'name' => 'test',
    ]);

    $baseUri = BaseUri::from('api.openai.com/v1');
    $headers = Headers::withAuthorization(ApiKey::from('foo'))->withContentType(ContentType::JSON);
    $queryParams = QueryParams::create();

    expect($payload->toRequest($baseUri, $headers, $queryParams)->getBody()->getContents())->toBe(json_encode([
        'name' => 'test',
    ]));
});
