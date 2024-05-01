<?php

use Anthropic\Responses\Meta\MetaInformation;

function metaHeaders(): array
{
    return [
        'anthropic-ratelimit-requests-limit' => [5],
        'anthropic-ratelimit-requests-remaining' => [4],
        'anthropic-ratelimit-requests-reset' => ['2024-04-30T15:56:17Z'],
        'anthropic-ratelimit-tokens-limit' => [25000],
        'anthropic-ratelimit-tokens-remaining' => [25000],
        'anthropic-ratelimit-tokens-reset' => ['2024-04-30T15:56:17Z'],
        'request-id' => ['02c10373f63cf2954851197d75c0adab'],
    ];
}

function metaHeadersWithDifferentCases(): array
{
    return [
        'Anthropic-Ratelimit-Requests-Limit' => [5],
        'Anthropic-Ratelimit-Requests-Remaining' => [4],
        'Anthropic-Ratelimit-Requests-Reset' => ['2024-04-30T15:56:17Z'],
        'Anthropic-Ratelimit-Tokens-Limit' => [25000],
        'Anthropic-Ratelimit-Tokens-Remaining' => [25000],
        'Anthropic-Ratelimit-Tokens-Reset' => ['2024-04-30T15:56:17Z'],
        'Request-Id' => ['02c10373f63cf2954851197d75c0adab'],
    ];
}

function meta(): MetaInformation
{
    return MetaInformation::from(metaHeaders());
}
