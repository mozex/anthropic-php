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
        'anthropic-ratelimit-input-tokens-limit' => [20000],
        'anthropic-ratelimit-input-tokens-remaining' => [19500],
        'anthropic-ratelimit-input-tokens-reset' => ['2024-04-30T15:56:17Z'],
        'anthropic-ratelimit-output-tokens-limit' => [5000],
        'anthropic-ratelimit-output-tokens-remaining' => [4900],
        'anthropic-ratelimit-output-tokens-reset' => ['2024-04-30T15:56:17Z'],
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
        'Anthropic-Ratelimit-Input-Tokens-Limit' => [20000],
        'Anthropic-Ratelimit-Input-Tokens-Remaining' => [19500],
        'Anthropic-Ratelimit-Input-Tokens-Reset' => ['2024-04-30T15:56:17Z'],
        'Anthropic-Ratelimit-Output-Tokens-Limit' => [5000],
        'Anthropic-Ratelimit-Output-Tokens-Remaining' => [4900],
        'Anthropic-Ratelimit-Output-Tokens-Reset' => ['2024-04-30T15:56:17Z'],
        'Request-Id' => ['02c10373f63cf2954851197d75c0adab'],
    ];
}

function metaHeadersWithPriority(): array
{
    return [
        ...metaHeaders(),
        'anthropic-priority-input-tokens-limit' => [50000],
        'anthropic-priority-input-tokens-remaining' => [48000],
        'anthropic-priority-input-tokens-reset' => ['2024-04-30T15:56:17Z'],
        'anthropic-priority-output-tokens-limit' => [10000],
        'anthropic-priority-output-tokens-remaining' => [9500],
        'anthropic-priority-output-tokens-reset' => ['2024-04-30T15:56:17Z'],
    ];
}

function meta(): MetaInformation
{
    return MetaInformation::from(metaHeaders());
}
