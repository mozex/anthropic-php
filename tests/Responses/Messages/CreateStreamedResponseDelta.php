<?php

use Anthropic\Responses\Messages\CreateStreamedResponseDelta;

test('from first chunk', function () {
    $result = CreateStreamedResponseDelta::from([]);

    expect($result)
        ->type->toBeNull()
        ->text->toBeNull()
        ->stop_reason->toBeNull()
        ->stop_sequence->toBeNull();
});

test('from content chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamContentChunk()['delta']);

    expect($result)
        ->type->toBe('text_delta')
        ->text->toBe('Hello')
        ->stop_reason->toBeNull()
        ->stop_sequence->toBeNull();
});

test('from last chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamLastChunk()['delta']);

    expect($result)
        ->type->toBeNull()
        ->text->toBeNull()
        ->stop_reason->toBe('end_turn')
        ->stop_sequence->toBeNull();
});

test('to array from first chunk', function () {
    $result = CreateStreamedResponseDelta::from([]);

    expect($result->toArray())
        ->toBe([
            'type' => null,
            'text' => null,
            'stop_reason' => null,
            'stop_sequence' => null,
        ]);
});

test('to array for a content chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamContentChunk()['delta']);

    expect($result->toArray())
        ->toBe([
            'type' => 'text_delta',
            'text' => 'Hello',
            'stop_reason' => null,
            'stop_sequence' => null,
        ]);
});

test('to array from last chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamLastChunk()['delta']);

    expect($result->toArray())
        ->toBe([
            'type' => null,
            'text' => null,
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
        ]);
});
