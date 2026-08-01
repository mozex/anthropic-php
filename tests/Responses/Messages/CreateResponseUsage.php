<?php

use Anthropic\Responses\Messages\CreateResponseUsage;
use Anthropic\Responses\Messages\CreateResponseUsageCacheCreation;
use Anthropic\Responses\Messages\CreateResponseUsageIteration;
use Anthropic\Responses\Messages\CreateResponseUsageOutputTokensDetails;
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
        ->serverToolUse->webSearchRequests->toBe(3)
        ->inferenceGeo->toBe('us');
});

test('from without inference geo', function () {
    $result = CreateResponseUsage::from(messagesCompletion()['usage']);

    expect($result)->inferenceGeo->toBeNull();
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

test('from with fast mode speed', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithFastMode()['usage']);

    expect($result)
        ->inputTokens->toBe(8)
        ->outputTokens->toBe(12)
        ->speed->toBe('fast')
        ->outputTokensDetails->toBeNull()
        ->iterations->toBeNull();
});

test('from with output tokens details', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithThinkingTokens()['usage']);

    expect($result)
        ->outputTokensDetails->toBeInstanceOf(CreateResponseUsageOutputTokensDetails::class)
        ->outputTokensDetails->thinkingTokens->toBe(150)
        ->speed->toBeNull();
});

test('from with iterations', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithFallback()['usage']);

    expect($result)
        ->inputTokens->toBe(412)
        ->outputTokens->toBe(264)
        ->iterations->toBeArray()
        ->iterations->toHaveCount(2);

    expect($result->iterations[0])
        ->toBeInstanceOf(CreateResponseUsageIteration::class)
        ->type->toBe('message')
        ->model->toBe('claude-fable-5')
        ->inputTokens->toBe(535)
        ->outputTokens->toBe(0)
        ->cacheReadInputTokens->toBe(0)
        ->cacheCreationInputTokens->toBe(0);

    expect($result->iterations[1])
        ->toBeInstanceOf(CreateResponseUsageIteration::class)
        ->type->toBe('fallback_message')
        ->model->toBe('claude-opus-4-8')
        ->inputTokens->toBe(412)
        ->outputTokens->toBe(264);
});

test('to array with fast mode speed', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithFastMode()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionWithFastMode()['usage']);
});

test('to array with output tokens details', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithThinkingTokens()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionWithThinkingTokens()['usage']);
});

test('to array with iterations', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithFallback()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionWithFallback()['usage']);
});

test('from with iterations without model', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithContextManagement()['usage']);

    expect($result->iterations)
        ->toBeArray()->toHaveCount(1);

    expect($result->iterations[0])
        ->toBeInstanceOf(CreateResponseUsageIteration::class)
        ->type->toBe('message')
        ->model->toBeNull()
        ->cacheCreation->toBeInstanceOf(CreateResponseUsageCacheCreation::class);
});

test('to array with iterations without model', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithContextManagement()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionWithContextManagement()['usage']);
});
