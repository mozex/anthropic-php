<?php

declare(strict_types=1);

use Anthropic\ValueObjects\Transporter\BaseUri;

it('can be created from a string', function () {
    $baseUri = BaseUri::from('api.anthropic.com/v1');

    expect($baseUri->toString())->toBe('https://api.anthropic.com/v1/');
});

it('can be created from a string with http protocol', function () {
    $baseUri = BaseUri::from('http://api.anthropic.com/v1');

    expect($baseUri->toString())->toBe('http://api.anthropic.com/v1/');
});

it('can be created from a string with https protocol', function () {
    $baseUri = BaseUri::from('https://api.anthropic.com/v1');

    expect($baseUri->toString())->toBe('https://api.anthropic.com/v1/');
});

it('keeps a base URI as the full API root, adding no path', function (string $given, string $expected) {
    expect(BaseUri::from($given)->toString())->toBe($expected);
})->with([
    'a gateway path' => ['https://gateway.example.com/anthropic', 'https://gateway.example.com/anthropic/'],
    'a host serving the API at its root' => ['https://proxy.example.com', 'https://proxy.example.com/'],
    'a host and port' => ['localhost:8080', 'https://localhost:8080/'],
]);

it('drops a trailing slash so the path is not doubled', function () {
    expect(BaseUri::from('https://api.anthropic.com/v1/')->toString())->toBe('https://api.anthropic.com/v1/')
        ->and(BaseUri::from('gateway.example.com/anthropic/')->toString())->toBe('https://gateway.example.com/anthropic/')
        ->and(BaseUri::from('https://proxy.example.com/')->toString())->toBe('https://proxy.example.com/');
});
