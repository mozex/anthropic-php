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
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
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

function messagesCompletionWithCache(): array
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
            'cache_creation_input_tokens' => 30,
            'cache_read_input_tokens' => 40,
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

/**
 * @return array<string, mixed>
 */
function messagesCompletionWithToolCalls(): array
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
                'text' => "I'll help you check the current weather in San Francisco. I'll use the get_weather function to find this information.",
            ],
            [
                'type' => 'tool_use',
                'id' => 'toolu_016udJr9epWhTNC8Ec1mnVQf',
                'name' => 'get_weather',
                'input' => [
                    'location' => 'San Francisco, CA',
                ],
            ],
        ],
        'stop_reason' => 'tool_use',
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

function messagesCompletionStreamContentBlockStartChunk(): array
{
    return [
        'type' => 'content_block_start',
        'index' => 0,
        'content_block' => [
            'type' => 'text',
            'text' => '',
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

function messagesCompletionStreamToolCallsContentBlockStartChunk(): array
{
    return [
        'type' => 'content_block_start',
        'index' => 1,
        'content_block' => [
            'type' => 'tool_use',
            'id' => 'toolu_01T1x8fJ34qAma2tNTrN7Up1',
            'name' => 'get_weather',
            'input' => [],
        ],
    ];
}

function messagesCompletionStreamToolCallsChunk(): array
{
    return [
        'type' => 'content_block_delta',
        'index' => 1,
        'delta' => [
            'type' => 'input_json_delta',
            'partial_json' => '{',
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
