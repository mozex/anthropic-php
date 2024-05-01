<?php

use Anthropic\Responses\Messages\CreateStreamedResponseMessage;

test('from first chunk', function () {
    $result = CreateStreamedResponseMessage::from(messagesCompletionStreamFirstChunk()['message']);

    expect($result)
        ->id->toBe('msg_01YS82gyNJHzAN1xVt2ymmTN')
        ->type->toBe('message')
        ->role->toBe('assistant')
        ->content->toBe([])
        ->model->toBe('claude-3-haiku-20240307')
        ->stop_reason->toBeNull()
        ->stop_sequence->toBeNull();
});

test('from content chunk', function () {
    $result = CreateStreamedResponseMessage::from([]);

    expect($result)
        ->id->toBeNull()
        ->type->toBeNull()
        ->role->toBeNull()
        ->content->toBeNull()
        ->model->toBeNull()
        ->stop_reason->toBeNull()
        ->stop_sequence->toBeNull();
});

test('to array from first chunk', function () {
    $result = CreateStreamedResponseMessage::from(messagesCompletionStreamFirstChunk()['message']);

    expect($result->toArray())
        ->toBe([
            'id' => 'msg_01YS82gyNJHzAN1xVt2ymmTN',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [],
            'model' => 'claude-3-haiku-20240307',
            'stop_reason' => null,
            'stop_sequence' => null,
        ]);
});

test('to array for a content chunk', function () {
    $result = CreateStreamedResponseMessage::from([]);

    expect($result->toArray())
        ->toBe([
            'id' => null,
            'type' => null,
            'role' => null,
            'content' => null,
            'model' => null,
            'stop_reason' => null,
            'stop_sequence' => null,
        ]);
});
