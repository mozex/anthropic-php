<?php

use Anthropic\Responses\Messages\CreateStreamedResponseContentBlockStart;

test('from first chunk', function () {
    $result = CreateStreamedResponseContentBlockStart::from(messagesCompletionStreamContentBlockStartChunk()['content_block']);

    expect($result)
        ->type->toBe('text')
        ->text->toBe('')
        ->id->toBeNull()
        ->name->toBeNull()
        ->input->toBeNull();
});

test('from first chunk of tool calls', function () {
    $result = CreateStreamedResponseContentBlockStart::from(messagesCompletionStreamToolCallsContentBlockStartChunk()['content_block']);

    expect($result)
        ->type->toBe('tool_use')
        ->text->toBeNull()
        ->id->toBe('toolu_01T1x8fJ34qAma2tNTrN7Up1')
        ->name->toBe('get_weather')
        ->input->toBe([]);
});

test('from first chunk of thinking', function () {
    $result = CreateStreamedResponseContentBlockStart::from(messagesCompletionStreamThinkingContentBlockStartChunk()['content_block']);

    expect($result)
        ->type->toBe('thinking')
        ->thinking->toBe('')
        ->text->toBeNull()
        ->id->toBeNull()
        ->name->toBeNull()
        ->input->toBeNull();
});

test('from content chunk', function () {
    $result = CreateStreamedResponseContentBlockStart::from([]);

    expect($result)
        ->type->toBeNull()
        ->text->toBeNull()
        ->id->toBeNull()
        ->name->toBeNull()
        ->input->toBeNull();
});

test('to array from first chunk', function () {
    $result = CreateStreamedResponseContentBlockStart::from(messagesCompletionStreamContentBlockStartChunk()['content_block']);

    expect($result->toArray())
        ->toBe([
            'id' => null,
            'type' => 'text',
            'text' => '',
            'name' => null,
            'input' => null,
            'thinking' => null,
        ]);
});

test('to array from first chunk of tool calls', function () {
    $result = CreateStreamedResponseContentBlockStart::from(messagesCompletionStreamToolCallsContentBlockStartChunk()['content_block']);

    expect($result->toArray())
        ->toBe([
            'id' => 'toolu_01T1x8fJ34qAma2tNTrN7Up1',
            'type' => 'tool_use',
            'text' => null,
            'name' => 'get_weather',
            'input' => [],
            'thinking' => null,
        ]);
});

test('to array from first chunk of thinking', function () {
    $result = CreateStreamedResponseContentBlockStart::from(messagesCompletionStreamThinkingContentBlockStartChunk()['content_block']);

    expect($result->toArray())
        ->toBe([
            'id' => null,
            'type' => 'thinking',
            'text' => null,
            'name' => null,
            'input' => null,
            'thinking' => '',
        ]);
});

test('to array for a content chunk', function () {
    $result = CreateStreamedResponseContentBlockStart::from([]);

    expect($result->toArray())
        ->toBe([
            'id' => null,
            'type' => null,
            'text' => null,
            'name' => null,
            'input' => null,
            'thinking' => null,
        ]);
});
