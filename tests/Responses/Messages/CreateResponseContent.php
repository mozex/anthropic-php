<?php

use Anthropic\Responses\Messages\CreateResponseContent;

test('from', function () {
    $result = CreateResponseContent::from(chatCompletion()['content'][0]);

    expect($result)
        ->type->toBe('text')
        ->text->toBe("Hello! I'm Claude, an AI assistant. How can I help you today?");
});

test('to array', function () {
    $result = CreateResponseContent::from(chatCompletion()['content'][0]);

    expect($result->toArray())
        ->toBe(chatCompletion()['content'][0]);
});
