<?php

declare(strict_types=1);

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Meta\MetaInformationCustom;
use Anthropic\Responses\Meta\MetaInformationRateLimit;
use GuzzleHttp\Psr7\Response;

test('from response headers', function () {
    $meta = MetaInformation::from((new Response(headers: metaHeaders()))->getHeaders());

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
        ->tokenLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->inputTokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->inputTokenLimit->limit->toBe(20000)
        ->inputTokenLimit->remaining->toBe(19500)
        ->inputTokenLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->outputTokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->outputTokenLimit->limit->toBe(5000)
        ->outputTokenLimit->remaining->toBe(4900)
        ->outputTokenLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->priorityInputTokenLimit->toBeNull()
        ->priorityOutputTokenLimit->toBeNull();
});

test('from response headers with priority tier', function () {
    $meta = MetaInformation::from((new Response(headers: metaHeadersWithPriority()))->getHeaders());

    expect($meta)
        ->priorityInputTokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->priorityInputTokenLimit->limit->toBe(50000)
        ->priorityInputTokenLimit->remaining->toBe(48000)
        ->priorityInputTokenLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->priorityOutputTokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->priorityOutputTokenLimit->limit->toBe(10000)
        ->priorityOutputTokenLimit->remaining->toBe(9500)
        ->priorityOutputTokenLimit->reset->toBe('2024-04-30T15:56:17Z');

    expect($meta->custom->toArray())->toBe([]);
});

test('to array with priority tier headers', function () {
    $meta = MetaInformation::from(metaHeadersWithPriority());

    expect($meta->toArray())
        ->toHaveKey('anthropic-priority-input-tokens-limit', 50000)
        ->toHaveKey('anthropic-priority-input-tokens-remaining', 48000)
        ->toHaveKey('anthropic-priority-input-tokens-reset', '2024-04-30T15:56:17Z')
        ->toHaveKey('anthropic-priority-output-tokens-limit', 10000)
        ->toHaveKey('anthropic-priority-output-tokens-remaining', 9500)
        ->toHaveKey('anthropic-priority-output-tokens-reset', '2024-04-30T15:56:17Z');
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
    $meta = MetaInformation::from((new Response(headers: metaHeadersWithDifferentCases()))->getHeaders());

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
        ->tokenLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->inputTokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->inputTokenLimit->limit->toBe(20000)
        ->inputTokenLimit->remaining->toBe(19500)
        ->inputTokenLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->outputTokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->outputTokenLimit->limit->toBe(5000)
        ->outputTokenLimit->remaining->toBe(4900)
        ->outputTokenLimit->reset->toBe('2024-04-30T15:56:17Z');
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
            'anthropic-ratelimit-input-tokens-limit' => 20000,
            'anthropic-ratelimit-input-tokens-remaining' => 19500,
            'anthropic-ratelimit-input-tokens-reset' => '2024-04-30T15:56:17Z',
            'anthropic-ratelimit-output-tokens-limit' => 5000,
            'anthropic-ratelimit-output-tokens-remaining' => 4900,
            'anthropic-ratelimit-output-tokens-reset' => '2024-04-30T15:56:17Z',
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

    $array = $meta->toArray();

    expect($array)
        ->toHaveKey('custom')
        ->and($array['custom'])->toBe(['x-custom' => 'value']);
});

test('to array excludes custom key when no custom headers', function () {
    $meta = MetaInformation::from(metaHeaders());

    expect($meta->toArray())->not->toHaveKey('custom');
});

test('the organization, workspace, retry-after and fast mode headers are typed', function () {
    // composed: the docs name these headers without example values
    $meta = MetaInformation::from((new Response(headers: [
        ...metaHeaders(),
        'anthropic-organization-id' => ['8a3c3a4e-3f3b-4d2a-9c41-5f4e1b2a7c90'],
        'anthropic-workspace-id' => ['wrkspc_011CZkZaBF1tNoB5wlCeusgy'],
        'retry-after' => ['30'],
        'anthropic-fast-input-tokens-limit' => ['40000'],
        'anthropic-fast-input-tokens-remaining' => ['39000'],
        'anthropic-fast-input-tokens-reset' => ['2024-04-30T15:56:17Z'],
        'anthropic-fast-output-tokens-limit' => ['8000'],
        'anthropic-fast-output-tokens-remaining' => ['7900'],
        'anthropic-fast-output-tokens-reset' => ['2024-04-30T15:56:17Z'],
    ]))->getHeaders());

    expect($meta)
        ->organizationId->toBe('8a3c3a4e-3f3b-4d2a-9c41-5f4e1b2a7c90')
        ->workspaceId->toBe('wrkspc_011CZkZaBF1tNoB5wlCeusgy')
        ->retryAfter->toBe(30)
        ->fastInputTokenLimit->toBeInstanceOf(MetaInformationRateLimit::class)
        ->fastInputTokenLimit->limit->toBe(40000)
        ->fastInputTokenLimit->remaining->toBe(39000)
        ->fastInputTokenLimit->reset->toBe('2024-04-30T15:56:17Z')
        ->fastOutputTokenLimit->limit->toBe(8000)
        ->fastOutputTokenLimit->remaining->toBe(7900)
        ->fastOutputTokenLimit->reset->toBe('2024-04-30T15:56:17Z');

    expect($meta->toArray())
        ->toHaveKey('anthropic-organization-id', '8a3c3a4e-3f3b-4d2a-9c41-5f4e1b2a7c90')
        ->toHaveKey('anthropic-workspace-id', 'wrkspc_011CZkZaBF1tNoB5wlCeusgy')
        ->toHaveKey('retry-after', 30)
        ->toHaveKey('anthropic-fast-input-tokens-limit', 40000)
        ->toHaveKey('anthropic-fast-output-tokens-reset', '2024-04-30T15:56:17Z')
        ->and($meta['retry-after'])->toBe(30);
});

test('typed headers that used to be custom stay in custom', function () {
    // composed: the docs name these headers without example values
    $meta = MetaInformation::from([
        ...metaHeaders(),
        'anthropic-organization-id' => ['8a3c3a4e-3f3b-4d2a-9c41-5f4e1b2a7c90'],
        'retry-after' => ['30'],
        'anthropic-fast-input-tokens-remaining' => ['39000'],
    ]);

    expect($meta->custom->toArray())->toBe([
        'anthropic-organization-id' => '8a3c3a4e-3f3b-4d2a-9c41-5f4e1b2a7c90',
        'retry-after' => '30',
        'anthropic-fast-input-tokens-remaining' => '39000',
    ]);
});

test('a retry-after that is not a number of seconds is not typed', function () {
    $meta = MetaInformation::from([...metaHeaders(), 'retry-after' => ['Wed, 21 Oct 2026 07:28:00 GMT']]);

    expect($meta->retryAfter)->toBeNull()
        ->and($meta->custom->toArray())->toBe(['retry-after' => 'Wed, 21 Oct 2026 07:28:00 GMT'])
        ->and($meta->toArray())->not->toHaveKey('retry-after');
});

test('the new headers are null when absent', function () {
    expect(MetaInformation::from(metaHeaders()))
        ->organizationId->toBeNull()
        ->workspaceId->toBeNull()
        ->retryAfter->toBeNull()
        ->fastInputTokenLimit->toBeNull()
        ->fastOutputTokenLimit->toBeNull();
});

test('typed string headers built by hand from numbers become strings', function () {
    // composed: the docs name these headers without example values
    $meta = MetaInformation::from([
        'anthropic-organization-id' => [123],
        'anthropic-fast-input-tokens-remaining' => [5],
        'anthropic-fast-input-tokens-reset' => [1714492577],
    ]);

    expect($meta)
        ->organizationId->toBe('123')
        ->fastInputTokenLimit->remaining->toBe(5)
        ->fastInputTokenLimit->reset->toBe('1714492577');
});

test('a retry-after with leading zeros is read as seconds', function () {
    expect(MetaInformation::from([...metaHeaders(), 'retry-after' => ['007']])->retryAfter)->toBe(7)
        ->and(MetaInformation::from([...metaHeaders(), 'retry-after' => [30]])->retryAfter)->toBe(30);
});
