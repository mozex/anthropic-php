<?php

use Anthropic\Responses\Messages\CreateResponseUsage;

test('from', function () {
    $result = CreateResponseUsage::from(messagesCompletion()['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(20)
        ->cacheCreationInputTokens->toBe(0)
        ->cacheReadInputTokens->toBe(0);
});

test('from with cache', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithCache()['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(20)
        ->cacheCreationInputTokens->toBe(30)
        ->cacheReadInputTokens->toBe(40);
});

test('to array', function () {
    $result = CreateResponseUsage::from(messagesCompletion()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletion()['usage']);
});

test('to array wit cache', function () {
    $result = CreateResponseUsage::from(messagesCompletionWithCache()['usage']);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_creation_input_tokens' => 30,
            'cache_read_input_tokens' => 40,
        ]);
});
