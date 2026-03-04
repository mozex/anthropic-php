<?php

use Anthropic\Responses\Messages\CreateStreamedResponseUsage;

test('from first chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunk()['message']['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(1)
        ->cacheCreationInputTokens->toBeNull()
        ->cacheReadInputTokens->toBeNull();
});

test('from content chunk', function () {
    $result = CreateStreamedResponseUsage::from([]);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBeNull()
        ->cacheCreationInputTokens->toBeNull()
        ->cacheReadInputTokens->toBeNull();
});

test('from last chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunk()['usage']);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBe(15)
        ->cacheCreationInputTokens->toBeNull()
        ->cacheReadInputTokens->toBeNull();
});

test('from first chunk with cache', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunkWithCache()['message']['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(1)
        ->cacheCreationInputTokens->toBe(30)
        ->cacheReadInputTokens->toBe(40);
});

test('from last chunk with cache', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunkWithCache()['usage']);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBe(15)
        ->cacheCreationInputTokens->toBe(30)
        ->cacheReadInputTokens->toBe(40);
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
