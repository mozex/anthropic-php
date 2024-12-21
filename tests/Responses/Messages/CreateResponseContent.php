<?php

use Anthropic\Responses\Messages\CreateResponseContent;

test('from', function () {
    $result = CreateResponseContent::from(messagesCompletion()['content'][0]);

    expect($result)
        ->type->toBe('text')
        ->text->toBe("Hello! I'm Claude, an AI assistant. How can I help you today?");
});

test('from tool calls response', function () {
    $result = CreateResponseContent::from(messagesCompletionWithToolCalls()['content'][1]);

    expect($result)
        ->type->toBe('tool_use')
        ->id->toBe('toolu_016udJr9epWhTNC8Ec1mnVQf')
        ->name->toBe('get_weather')
        ->input->toBe(['location' => 'San Francisco, CA']);
});

test('to array', function () {
    $result = CreateResponseContent::from(messagesCompletion()['content'][0]);

    expect($result->toArray())
        ->toBe(messagesCompletion()['content'][0]);
});

test('to array from tool calls response', function () {
    $result = CreateResponseContent::from(messagesCompletionWithToolCalls()['content'][1]);

    expect($result->toArray())
        ->toBe(messagesCompletionWithToolCalls()['content'][1]);
});
