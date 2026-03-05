<?php

use Anthropic\Responses\Messages\CreateResponseUsage;
use Anthropic\Responses\Messages\CreateResponseUsageCacheCreation;
use Anthropic\Responses\Messages\CreateResponseUsageServerToolUse;

test('from', function () {
    $result = CreateResponseUsage::from(messagesCompletion()['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(20)
        ->cacheCreationInputTokens->toBe(0)
        ->cacheReadInputTokens->toBe(0)
        ->cacheCreation->toBeNull()
        ->serviceTier->toBeNull()
        ->serverToolUse->toBeNull();
});

test('from with cache', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithCache()['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(20)
        ->cacheCreationInputTokens->toBe(30)
        ->cacheReadInputTokens->toBe(40)
        ->cacheCreation->toBeNull()
        ->serviceTier->toBeNull()
        ->serverToolUse->toBeNull();
});

test('from with extended usage', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithExtendedUsage()['usage']);

    expect($result)
        ->inputTokens->toBe(2048)
        ->outputTokens->toBe(503)
        ->cacheCreationInputTokens->toBe(248)
        ->cacheReadInputTokens->toBe(1800)
        ->cacheCreation->toBeInstanceOf(CreateResponseUsageCacheCreation::class)
        ->cacheCreation->ephemeral5mInputTokens->toBe(148)
        ->cacheCreation->ephemeral1hInputTokens->toBe(100)
        ->serviceTier->toBe('standard')
        ->serverToolUse->toBeInstanceOf(CreateResponseUsageServerToolUse::class)
        ->serverToolUse->webSearchRequests->toBe(3);
});

test('to array', function () {
    $result = CreateResponseUsage::from(messagesCompletion()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletion()['usage']);
});

test('to array with cache', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithCache()['usage']);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_creation_input_tokens' => 30,
            'cache_read_input_tokens' => 40,
        ]);
});

test('to array with extended usage', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithExtendedUsage()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionWithExtendedUsage()['usage']);
});
