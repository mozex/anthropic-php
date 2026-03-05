<?php

use Anthropic\Responses\Messages\CreateResponseUsageCacheCreation;
use Anthropic\Responses\Messages\CreateResponseUsageServerToolUse;
use Anthropic\Responses\Messages\CreateStreamedResponseUsage;

test('from first chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunk()['message']['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(1)
        ->cacheCreationInputTokens->toBeNull()
        ->cacheReadInputTokens->toBeNull()
        ->cacheCreation->toBeNull()
        ->serviceTier->toBeNull()
        ->serverToolUse->toBeNull();
});

test('from content chunk', function () {
    $result = CreateStreamedResponseUsage::from([]);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBeNull()
        ->cacheCreationInputTokens->toBeNull()
        ->cacheReadInputTokens->toBeNull()
        ->cacheCreation->toBeNull()
        ->serviceTier->toBeNull()
        ->serverToolUse->toBeNull();
});

test('from last chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunk()['usage']);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBe(15)
        ->cacheCreationInputTokens->toBeNull()
        ->cacheReadInputTokens->toBeNull()
        ->cacheCreation->toBeNull()
        ->serviceTier->toBeNull()
        ->serverToolUse->toBeNull();
});

test('from first chunk with cache', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunkWithCache()['message']['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(1)
        ->cacheCreationInputTokens->toBe(30)
        ->cacheReadInputTokens->toBe(40)
        ->cacheCreation->toBeNull()
        ->serviceTier->toBeNull()
        ->serverToolUse->toBeNull();
});

test('from last chunk with cache', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunkWithCache()['usage']);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBe(15)
        ->cacheCreationInputTokens->toBe(30)
        ->cacheReadInputTokens->toBe(40)
        ->cacheCreation->toBeNull()
        ->serviceTier->toBeNull()
        ->serverToolUse->toBeNull();
});

test('from first chunk with extended usage', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunkWithExtendedUsage()['message']['usage']);

    expect($result)
        ->inputTokens->toBe(2048)
        ->outputTokens->toBe(1)
        ->cacheCreationInputTokens->toBe(248)
        ->cacheReadInputTokens->toBe(1800)
        ->cacheCreation->toBeInstanceOf(CreateResponseUsageCacheCreation::class)
        ->cacheCreation->ephemeral5mInputTokens->toBe(148)
        ->cacheCreation->ephemeral1hInputTokens->toBe(100)
        ->serviceTier->toBe('standard')
        ->serverToolUse->toBeInstanceOf(CreateResponseUsageServerToolUse::class)
        ->serverToolUse->webSearchRequests->toBe(3);
});

test('from last chunk with extended usage', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunkWithExtendedUsage()['usage']);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBe(15)
        ->cacheCreationInputTokens->toBeNull()
        ->cacheReadInputTokens->toBeNull()
        ->cacheCreation->toBeNull()
        ->serviceTier->toBe('standard')
        ->serverToolUse->toBeInstanceOf(CreateResponseUsageServerToolUse::class)
        ->serverToolUse->webSearchRequests->toBe(3);
});

test('to array from first chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunk()['message']['usage']);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => 10,
            'output_tokens' => 1,
            'cache_creation_input_tokens' => null,
            'cache_read_input_tokens' => null,
        ]);
});

test('to array for a content chunk', function () {
    $result = CreateStreamedResponseUsage::from([]);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => null,
            'output_tokens' => null,
            'cache_creation_input_tokens' => null,
            'cache_read_input_tokens' => null,
        ]);
});

test('to array from last chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunk()['usage']);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => null,
            'output_tokens' => messagesCompletionStreamLastChunk()['usage']['output_tokens'],
            'cache_creation_input_tokens' => null,
            'cache_read_input_tokens' => null,
        ]);
});

test('to array from first chunk with cache', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunkWithCache()['message']['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionStreamFirstChunkWithCache()['message']['usage']);
});

test('to array from last chunk with cache', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunkWithCache()['usage']);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => null,
            'output_tokens' => messagesCompletionStreamLastChunkWithCache()['usage']['output_tokens'],
            'cache_creation_input_tokens' => 30,
            'cache_read_input_tokens' => 40,
        ]);
});

test('to array from first chunk with extended usage', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunkWithExtendedUsage()['message']['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionStreamFirstChunkWithExtendedUsage()['message']['usage']);
});

test('to array from last chunk with extended usage', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunkWithExtendedUsage()['usage']);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => null,
            'output_tokens' => 15,
            'cache_creation_input_tokens' => null,
            'cache_read_input_tokens' => null,
            'service_tier' => 'standard',
            'server_tool_use' => [
                'web_search_requests' => 3,
            ],
        ]);
});
