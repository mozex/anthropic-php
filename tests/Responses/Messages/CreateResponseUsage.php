<?php

use Anthropic\Responses\Messages\CreateResponseUsage;

test('from', function () {
    $result = CreateResponseUsage::from(messagesCompletion()['usage']);

    expect($result)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(20);
});

test('to array', function () {
    $result = CreateResponseUsage::from(messagesCompletion()['usage']);

    expect($result->toArray())
        ->toBe(messagesCompletion()['usage']);
});
