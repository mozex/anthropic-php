<?php

use Anthropic\Responses\Messages\CreateStreamedResponseUsage;

test('from first chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunk()['message']['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(1);
});

test('from content chunk', function () {
    $result = CreateStreamedResponseUsage::from([]);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBeNull();
});

test('from last chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunk()['usage']);

    expect($result)
        ->inputTokens->toBeNull()
        ->outputTokens->toBe(15);
});

test('to array from first chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamFirstChunk()['message']['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletionStreamFirstChunk()['message']['usage']);
});

test('to array for a content chunk', function () {
    $result = CreateStreamedResponseUsage::from([]);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => null,
            'output_tokens' => null,
        ]);
});

test('to array from last chunk', function () {
    $result = CreateStreamedResponseUsage::from(messagesCompletionStreamLastChunk()['usage']);

    expect($result->toArray())
        ->toBe([
            'input_tokens' => null,
            'output_tokens' => messagesCompletionStreamLastChunk()['usage']['output_tokens'],
        ]);
});
