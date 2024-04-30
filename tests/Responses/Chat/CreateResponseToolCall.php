<?php

use Anthropic\Responses\Chat\CreateResponseToolCall;
use Anthropic\Responses\Chat\CreateResponseToolCallFunction;

test('from', function () {
    $result = CreateResponseToolCall::from(chatCompletionWithToolCalls()['choices'][0]['message']['tool_calls'][0]);

    expect($result)
        ->id->toBe('call_trlgKnhMpYSC7CFXKw3CceUZ')
        ->type->toBe('function')
        ->function->toBeInstanceOf(CreateResponseToolCallFunction::class);
});

test('to array', function () {
    $result = CreateResponseToolCall::from(chatCompletionWithToolCalls()['choices'][0]['message']['tool_calls'][0]);

    expect($result->toArray())
        ->toBe(chatCompletionWithToolCalls()['choices'][0]['message']['tool_calls'][0]);
});
