<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'die', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('it uses strict types')
    ->expect('Anthropic')
    ->toUseStrictTypes();

arch('contracts')
    ->expect('Anthropic\Contracts')
    ->toOnlyUse([
        'Anthropic\ValueObjects',
        'Anthropic\Exceptions',
        'Anthropic\Resources',
        'Psr\Http\Message\ResponseInterface',
        'Anthropic\Responses',
    ])
    ->toBeInterfaces();

arch('enums')
    ->expect('Anthropic\Enums')
    ->toBeEnums();

arch('exceptions')
    ->expect('Anthropic\Exceptions')
    ->toOnlyUse([
        'Psr\Http\Client',
        'Psr\Http\Message\ResponseInterface',
    ])->toImplement(Throwable::class);

arch('resources')->expect('Anthropic\Resources')->toOnlyUse([
    'Anthropic\Contracts',
    'Anthropic\ValueObjects',
    'Anthropic\Exceptions',
    'Anthropic\Responses',
]);

arch('responses')->expect('Anthropic\Responses')->toOnlyUse([
    'Http\Discovery\Psr17Factory',
    'Anthropic\Enums',
    'Anthropic\Exceptions\ErrorException',
    'Anthropic\Contracts',
    'Anthropic\Testing\Responses\Concerns',
    'Psr\Http\Message\ResponseInterface',
    'Psr\Http\Message\StreamInterface',
]);

arch('value objects')->expect('Anthropic\ValueObjects')->toOnlyUse([
    'Http\Discovery\Psr17Factory',
    'Http\Message\MultipartStream\MultipartStreamBuilder',
    'Psr\Http\Message\RequestInterface',
    'Psr\Http\Message\StreamInterface',
    'Anthropic\Enums',
    'Anthropic\Contracts',
    'Anthropic\Exceptions\InvalidArgumentException',
    'Anthropic\Responses\Meta\MetaInformation',
]);

arch('client')->expect('Anthropic\Client')->toOnlyUse([
    'Anthropic\Resources',
    'Anthropic\Contracts',
]);

arch('anthropic')->expect('Anthropic')->toOnlyUse([
    'GuzzleHttp\Client',
    'GuzzleHttp\Exception\BadResponseException',
    'GuzzleHttp\Exception\RequestException',
    'Http\Discovery\Psr17Factory',
    'Http\Discovery\Psr18ClientDiscovery',
    'Http\Message\MultipartStream\MultipartStreamBuilder',
    'Anthropic\Contracts',
    'Anthropic\Resources',
    'Psr\Http\Client',
    'Psr\Http\Message\RequestInterface',
    'Psr\Http\Message\ResponseInterface',
    'Psr\Http\Message\StreamInterface',
    'Psr\Log\LoggerInterface',
])->ignoring('Anthropic\Testing');

test('every test file ends in Test.php', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
    $skipped = [];

    foreach ($files as $file) {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(__DIR__) + 1));

        // Pest loads these itself.
        if ($path === 'Pest.php' || str_starts_with($path, 'Helpers/')) {
            continue;
        }

        if ($file->getExtension() === 'php' && ! str_ends_with($path, 'Test.php')) {
            $skipped[] = $path;
        }
    }

    expect($skipped)->toBe([]);
});
