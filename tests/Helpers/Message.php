<?php

/**
 * @return array<string, mixed>
 */
function messagesCompletion(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-3-opus-20240229',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 10,
            'output_tokens' => 20,
        ],
        'content' => [
            [
                'type' => 'text',
                'text' => "Hello! I'm Claude, an AI assistant. How can I help you today?",
            ],
        ],
        'stop_reason' => 'end_turn',
    ];
}

function messagesCompletionStreamFirstChunk(): array
{
    return [
        'type' => 'message_start',
        'message' => [
            'id' => 'msg_01YS82gyNJHzAN1xVt2ymmTN',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [],
            'model' => 'claude-3-haiku-20240307',
            'stop_reason' => null,
            'stop_sequence' => null,
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 1,
            ],
        ],
    ];
}

function messagesCompletionStreamLastChunk(): array
{
    return [
        'type' => 'message_delta',
        'delta' => [
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
        ],
        'usage' => [
            'output_tokens' => 15,
        ],
    ];
}

function messagesCompletionStreamContentChunk(): array
{
    return [
        'type' => 'content_block_delta',
        'index' => 0,
        'delta' => [
            'type' => 'text_delta',
            'text' => 'Hello',
        ],
    ];
}

/**
 * @return resource
 */
function messagesCompletionStream()
{
    return fopen(__DIR__.'/Streams/MessagesCompletionCreate.txt', 'r');
}

/**
 * @return resource
 */
function messagesCompletionStreamError()
{
    return fopen(__DIR__.'/Streams/MessagesCompletionCreateError.txt', 'r');
}
