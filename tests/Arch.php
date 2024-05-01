<?php

test('contracts')
    ->expect('Anthropic\Contracts')
    ->toOnlyUse([
        'Anthropic\ValueObjects',
        'Anthropic\Exceptions',
        'Anthropic\Resources',
        'Psr\Http\Message\ResponseInterface',
        'Anthropic\Responses',
    ])
    ->toBeInterfaces();

test('enums')
    ->expect('Anthropic\Enums')
    ->toBeEnums();

test('exceptions')
    ->expect('Anthropic\Exceptions')
    ->toOnlyUse([
        'Psr\Http\Client',
    ])->toImplement(Throwable::class);

test('resources')->expect('Anthropic\Resources')->toOnlyUse([
    'Anthropic\Contracts',
    'Anthropic\ValueObjects',
    'Anthropic\Exceptions',
    'Anthropic\Responses',
]);

test('responses')->expect('Anthropic\Responses')->toOnlyUse([
    'Http\Discovery\Psr17Factory',
    'Anthropic\Enums',
    'Anthropic\Exceptions\ErrorException',
    'Anthropic\Contracts',
    'Anthropic\Testing\Responses\Concerns',
    'Psr\Http\Message\ResponseInterface',
    'Psr\Http\Message\StreamInterface',
]);

test('value objects')->expect('Anthropic\ValueObjects')->toOnlyUse([
    'Http\Discovery\Psr17Factory',
    'Http\Message\MultipartStream\MultipartStreamBuilder',
    'Psr\Http\Message\RequestInterface',
    'Psr\Http\Message\StreamInterface',
    'Anthropic\Enums',
    'Anthropic\Contracts',
    'Anthropic\Responses\Meta\MetaInformation',
]);

test('client')->expect('Anthropic\Client')->toOnlyUse([
    'Anthropic\Resources',
    'Anthropic\Contracts',
]);

test('anthropic')->expect('Anthropic')->toOnlyUse([
    'GuzzleHttp\Client',
    'GuzzleHttp\Exception\ClientException',
    'Http\Discovery\Psr17Factory',
    'Http\Discovery\Psr18ClientDiscovery',
    'Http\Message\MultipartStream\MultipartStreamBuilder',
    'Anthropic\Contracts',
    'Anthropic\Resources',
    'Psr\Http\Client',
    'Psr\Http\Message\RequestInterface',
    'Psr\Http\Message\ResponseInterface',
    'Psr\Http\Message\StreamInterface',
])->ignoring('Anthropic\Testing');

test('Not debugging statements are left in our code.')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'die', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();
