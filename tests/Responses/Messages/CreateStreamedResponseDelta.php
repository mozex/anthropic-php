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

test('from tool calls chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamToolCallsChunk()['delta']);

    expect($result)
        ->type->toBe('input_json_delta')
        ->partial_json->toBe('{')
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

test('from thinking delta chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamThinkingDeltaChunk()['delta']);

    expect($result)
        ->type->toBe('thinking_delta')
        ->thinking->toBe('I need to find the GCD using the Euclidean algorithm.')
        ->text->toBeNull()
        ->signature->toBeNull()
        ->stop_reason->toBeNull()
        ->stop_sequence->toBeNull();
});

test('from signature delta chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamSignatureDeltaChunk()['delta']);

    expect($result)
        ->type->toBe('signature_delta')
        ->signature->toBe('EqQBCgIYAhIM1gbcDa9GJwZA2b3h')
        ->thinking->toBeNull()
        ->text->toBeNull()
        ->stop_reason->toBeNull()
        ->stop_sequence->toBeNull();
});

test('from citations delta chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamCitationsDeltaChunk()['delta']);

    expect($result)
        ->type->toBe('citations_delta')
        ->citation->toBeArray()
        ->text->toBeNull()
        ->stop_reason->toBeNull();

    expect($result->citation)
        ->toBe([
            'type' => 'char_location',
            'cited_text' => 'The grass is green.',
            'document_index' => 0,
            'document_title' => 'Example Document',
            'start_char_index' => 0,
            'end_char_index' => 20,
        ]);
});

test('to array for a citations delta chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamCitationsDeltaChunk()['delta']);

    expect($result->toArray())
        ->toBe([
            'type' => 'citations_delta',
            'text' => null,
            'stop_reason' => null,
            'stop_sequence' => null,
            'citation' => [
                'type' => 'char_location',
                'cited_text' => 'The grass is green.',
                'document_index' => 0,
                'document_title' => 'Example Document',
                'start_char_index' => 0,
                'end_char_index' => 20,
            ],
        ]);
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

test('to array for a tool calls chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamToolCallsChunk()['delta']);

    expect($result->toArray())
        ->toBe([
            'type' => 'input_json_delta',
            'text' => null,
            'stop_reason' => null,
            'stop_sequence' => null,
            'partial_json' => '{',
        ]);
});

test('to array for a thinking delta chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamThinkingDeltaChunk()['delta']);

    expect($result->toArray())
        ->toBe([
            'type' => 'thinking_delta',
            'text' => null,
            'stop_reason' => null,
            'stop_sequence' => null,
            'thinking' => 'I need to find the GCD using the Euclidean algorithm.',
        ]);
});

test('to array for a signature delta chunk', function () {
    $result = CreateStreamedResponseDelta::from(messagesCompletionStreamSignatureDeltaChunk()['delta']);

    expect($result->toArray())
        ->toBe([
            'type' => 'signature_delta',
            'text' => null,
            'stop_reason' => null,
            'stop_sequence' => null,
            'signature' => 'EqQBCgIYAhIM1gbcDa9GJwZA2b3h',
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
