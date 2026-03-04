<?php

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Meta\MetaInformationCustom;
use Anthropic\Responses\Meta\MetaInformationRateLimit;

test('from response headers', function () {
    $meta = MetaInformation::from((new \GuzzleHttp\Psr7\Response(headers: metaHeaders()))->getHeaders());

    expect($meta)
        ->toBeInstanceOf(MetaInformation::class)
        ->requestId->toBe('02c10373f63cf2954851197d75c0adab')
        ->requestLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->requestLimit->limit->toBe(5)
        ->requestLimit->remaining->toBe(4)
        ->requestLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->tokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->tokenLimit->limit->toBe(25000)
        ->tokenLimit->remaining->toBe(25000)
        ->tokenLimit->reset->toBe('2024-04-30T15:56:17Z');
});

test('from response headers without "request-id"', function () {
    $headers = metaHeaders();
    unset($headers['request-id']);

    $meta = MetaInformation::from($headers);

    expect($meta)
        ->toBeInstanceOf(MetaInformation::class)
        ->requestId->toBeNull();
});

test('from response headers in different cases', function () {
    $meta = MetaInformation::from((new \GuzzleHttp\Psr7\Response(headers: metaHeadersWithDifferentCases()))->getHeaders());

    expect($meta)
        ->toBeInstanceOf(MetaInformation::class)
        ->requestId->toBe('02c10373f63cf2954851197d75c0adab')
        ->requestLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->requestLimit->limit->toBe(5)
        ->requestLimit->remaining->toBe(4)
        ->requestLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->tokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->tokenLimit->limit->toBe(25000)
        ->tokenLimit->remaining->toBe(25000)
        ->tokenLimit->reset->toBe('2024-04-30T15:56:17Z');
});

test('as array accessible', function () {
    $meta = MetaInformation::from(metaHeaders());

    expect($meta['request-id'])->toBe('02c10373f63cf2954851197d75c0adab');
});

test('to array', function () {
    $meta = MetaInformation::from(metaHeaders());

    expect($meta->toArray())
        ->toBeArray()
        ->toBe([
            'anthropic-ratelimit-requests-limit' => 5,
            'anthropic-ratelimit-tokens-limit' => 25000,
            'anthropic-ratelimit-requests-remaining' => 4,
            'anthropic-ratelimit-tokens-remaining' => 25000,
            'anthropic-ratelimit-requests-reset' => '2024-04-30T15:56:17Z',
            'anthropic-ratelimit-tokens-reset' => '2024-04-30T15:56:17Z',
            'request-id' => '02c10373f63cf2954851197d75c0adab',
        ]);
});

test('custom headers are captured', function () {
    $headers = [
        ...metaHeaders(),
        'x-custom-header' => ['custom-value'],
        'cf-ray' => ['abc123'],
    ];

    $meta = MetaInformation::from($headers);

    expect($meta->custom)
        ->toBeInstanceOf(MetaInformationCustom::class)
        ->and($meta->custom->toArray())
        ->toBe([
            'x-custom-header' => 'custom-value',
            'cf-ray' => 'abc123',
        ]);
});

test('custom headers exclude known headers', function () {
    $meta = MetaInformation::from(metaHeaders());

    expect($meta->custom->toArray())->toBe([]);
});

test('to array includes custom headers when present', function () {
    $headers = [
        ...metaHeaders(),
        'x-custom' => ['value'],
    ];

    $meta = MetaInformation::from($headers);

    expect($meta->toArray())
        ->toHaveKey('custom')
        ->and($meta->toArray()['custom'])->toBe(['x-custom' => 'value']);
});

test('to array excludes custom key when no custom headers', function () {
    $meta = MetaInformation::from(metaHeaders());

    expect($meta->toArray())->not->toHaveKey('custom');
});
