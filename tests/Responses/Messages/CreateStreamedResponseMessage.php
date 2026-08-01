<?php

use Anthropic\Responses\Messages\CreateResponseDiagnostics;
use Anthropic\Responses\Messages\CreateResponseDiagnosticsCacheMissReason;
use Anthropic\Responses\Messages\CreateStreamedResponseMessage;

test('from first chunk', function () {
    $result = CreateStreamedResponseMessage::from(messagesCompletionStreamFirstChunk()['message']);

    expect($result)
        ->id->toBe('msg_01YS82gyNJHzAN1xVt2ymmTN')
        ->type->toBe('message')
        ->role->toBe('assistant')
        ->content->toBe([])
        ->model->toBe('claude-haiku-4-5')
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
            'model' => 'claude-haiku-4-5',
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

test('from first chunk with diagnostics', function () {
    $result = CreateStreamedResponseMessage::from(messagesCompletionStreamFirstChunkWithDiagnostics()['message']);

    expect($result)
        ->id->toBe('msg_01YS82gyNJHzAN1xVt2ymmTN')
        ->diagnostics->toBeInstanceOf(CreateResponseDiagnostics::class);

    expect($result->diagnostics->cache_miss_reason)
        ->toBeInstanceOf(CreateResponseDiagnosticsCacheMissReason::class)
        ->type->toBe('system_changed')
        ->cache_missed_input_tokens->toBe(41850);
});

test('to array from first chunk with diagnostics', function () {
    $result = CreateStreamedResponseMessage::from(messagesCompletionStreamFirstChunkWithDiagnostics()['message']);

    expect($result->toArray())
        ->toBe([
            'id' => 'msg_01YS82gyNJHzAN1xVt2ymmTN',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [],
            'model' => 'claude-opus-5',
            'stop_reason' => null,
            'stop_sequence' => null,
            'diagnostics' => [
                'cache_miss_reason' => [
                    'type' => 'system_changed',
                    'cache_missed_input_tokens' => 41850,
                ],
            ],
        ]);
});
