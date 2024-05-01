<?php

use Anthropic\Enums\Transporter\ContentType;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\Headers;

it('can be created from an API Token', function () {
    $headers = Headers::withAuthorization(ApiKey::from('foo'));

    expect($headers->toArray())->toBe([
        'x-api-key' => 'foo',
    ]);
});

it('can have content/type', function () {
    $headers = Headers::withAuthorization(ApiKey::from('foo'))
        ->withContentType(ContentType::JSON);

    expect($headers->toArray())->toBe([
        'x-api-key' => 'foo',
        'Content-Type' => 'application/json',
    ]);
});

it('can have content/type with suffix', function () {
    $headers = Headers::withAuthorization(ApiKey::from('foo'))
        ->withContentType(ContentType::MULTIPART, '; boundary=---XYZ');

    expect($headers->toArray())->toBe([
        'x-api-key' => 'foo',
        'Content-Type' => 'multipart/form-data; boundary=---XYZ',
    ]);
});

it('can have custom header', function () {
    $headers = Headers::withAuthorization(ApiKey::from('foo'))
        ->withContentType(ContentType::JSON)
        ->withCustomHeader('X-Foo', 'bar');

    expect($headers->toArray())->toBe([
        'x-api-key' => 'foo',
        'Content-Type' => 'application/json',
        'X-Foo' => 'bar',
    ]);
});
