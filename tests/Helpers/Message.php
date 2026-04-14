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
        'model' => 'claude-sonnet-4-6',
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

function messagesCompletionWithThinking(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 10,
            'output_tokens' => 200,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ],
        'content' => [
            [
                'type' => 'thinking',
                'thinking' => 'Let me analyze this step by step...',
                'signature' => 'WaUjzkypQ2mUEVM36O2Txu',
            ],
            [
                'type' => 'redacted_thinking',
                'data' => 'EmwKAhgBEgy3va3pzix/LafPsn4a',
            ],
            [
                'type' => 'text',
                'text' => "Hello! I'm Claude, an AI assistant. How can I help you today?",
            ],
        ],
        'stop_reason' => 'end_turn',
    ];
}

function messagesCompletionWithOmittedThinking(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 10,
            'output_tokens' => 200,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ],
        'content' => [
            [
                'type' => 'thinking',
                'thinking' => '',
                'signature' => 'EosnCkYICxIMMb3LzNrMu',
            ],
            [
                'type' => 'text',
                'text' => 'The answer is 12,231.',
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
        'model' => 'claude-sonnet-4-6',
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

function messagesCompletionWithExtendedUsage(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 2048,
            'output_tokens' => 503,
            'cache_creation_input_tokens' => 248,
            'cache_read_input_tokens' => 1800,
            'cache_creation' => [
                'ephemeral_5m_input_tokens' => 148,
                'ephemeral_1h_input_tokens' => 100,
            ],
            'service_tier' => 'standard',
            'server_tool_use' => [
                'web_search_requests' => 3,
            ],
            'inference_geo' => 'us',
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
        'model' => 'claude-sonnet-4-6',
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

/**
 * @return array<string, mixed>
 */
function messagesCompletionWithToolCallsAndCaller(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ],
        'content' => [
            [
                'type' => 'tool_use',
                'id' => 'toolu_016udJr9epWhTNC8Ec1mnVQf',
                'name' => 'get_weather',
                'input' => [
                    'location' => 'San Francisco, CA',
                ],
                'caller' => [
                    'type' => 'direct',
                ],
            ],
            [
                'type' => 'server_tool_use',
                'id' => 'srvtoolu_01A2B3C4D5E6F7G8H9',
                'name' => 'bash_code_execution',
                'input' => [
                    'command' => 'ls -la',
                ],
                'caller' => [
                    'type' => 'code_execution_20250825',
                    'tool_id' => 'srvtoolu_parentCodeExec01',
                ],
            ],
        ],
        'stop_reason' => 'tool_use',
    ];
}

/**
 * @return array<string, mixed>
 */
function messagesCompletionWithContainerUpload(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ],
        'content' => [
            [
                'type' => 'container_upload',
                'file_id' => 'file_01ABCDefGhIjKlMnOpQrStUv',
            ],
        ],
        'stop_reason' => 'end_turn',
    ];
}

/**
 * @return array<string, mixed>
 */
function messagesCompletionWithWebSearch(): array
{
    return [
        'id' => 'msg_a930390d3a',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 6039,
            'output_tokens' => 931,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'server_tool_use' => [
                'web_search_requests' => 1,
            ],
        ],
        'content' => [
            [
                'type' => 'text',
                'text' => "I'll search for when Claude Shannon was born.",
            ],
            [
                'type' => 'server_tool_use',
                'id' => 'srvtoolu_01WYG3ziw53XMcoyKL4XcZmE',
                'name' => 'web_search',
                'input' => [
                    'query' => 'claude shannon birth date',
                ],
            ],
            [
                'type' => 'web_search_tool_result',
                'tool_use_id' => 'srvtoolu_01WYG3ziw53XMcoyKL4XcZmE',
                'content' => [
                    [
                        'type' => 'web_search_result',
                        'url' => 'https://en.wikipedia.org/wiki/Claude_Shannon',
                        'title' => 'Claude Shannon - Wikipedia',
                        'encrypted_content' => 'EqgfCioIARgBIiQ3YTAwMjY1Mi1mZjM5LTQ1NGUtODgxNC1kNjNjNTk1ZWI3Y',
                        'page_age' => 'April 30, 2025',
                    ],
                ],
            ],
            [
                'type' => 'text',
                'text' => 'Claude Shannon was born on April 30, 1916, in Petoskey, Michigan',
                'citations' => [
                    [
                        'type' => 'web_search_result_location',
                        'url' => 'https://en.wikipedia.org/wiki/Claude_Shannon',
                        'title' => 'Claude Shannon - Wikipedia',
                        'encrypted_index' => 'Eo8BCioIAhgBIiQyYjQ0OWJmZi1lNm',
                        'cited_text' => 'Claude Elwood Shannon (April 30, 1916 – February 24, 2001) was an American mathematician',
                    ],
                ],
            ],
        ],
        'stop_reason' => 'end_turn',
    ];
}

/**
 * @return array<string, mixed>
 */
function messagesCompletionWithCodeExecution(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 105,
            'output_tokens' => 239,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'server_tool_use' => [
                'code_execution_requests' => 1,
            ],
        ],
        'content' => [
            [
                'type' => 'server_tool_use',
                'id' => 'srvtoolu_01A2B3C4D5E6F7G8H9',
                'name' => 'code_execution',
                'input' => [
                    'code' => 'print("Hello, World!")',
                ],
            ],
            [
                'type' => 'code_execution_tool_result',
                'tool_use_id' => 'srvtoolu_01A2B3C4D5E6F7G8H9',
                'content' => [
                    'type' => 'code_execution_result',
                    'stdout' => 'Hello, World!',
                    'stderr' => '',
                    'return_code' => 0,
                ],
            ],
            [
                'type' => 'text',
                'text' => 'The code executed successfully.',
            ],
        ],
        'stop_reason' => 'end_turn',
        'container' => [
            'id' => 'container_123',
            'expires_at' => '2025-03-15T10:30:00Z',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function messagesCompletionWithRefusal(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 10,
            'output_tokens' => 5,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ],
        'content' => [
            [
                'type' => 'text',
                'text' => "I can't help with that request.",
            ],
        ],
        'stop_reason' => 'refusal',
        'stop_details' => [
            'type' => 'refusal',
            'category' => 'cyber',
            'explanation' => 'This request was flagged for a cybersecurity policy violation.',
        ],
    ];
}

function messagesCompletionStreamServerToolUseContentBlockStartChunk(): array
{
    return [
        'type' => 'content_block_start',
        'index' => 1,
        'content_block' => [
            'type' => 'server_tool_use',
            'id' => 'srvtoolu_01WYG3ziw53XMcoyKL4XcZmE',
            'name' => 'web_search',
        ],
    ];
}

function messagesCompletionStreamWebSearchResultContentBlockStartChunk(): array
{
    return [
        'type' => 'content_block_start',
        'index' => 2,
        'content_block' => [
            'type' => 'web_search_tool_result',
            'tool_use_id' => 'srvtoolu_01WYG3ziw53XMcoyKL4XcZmE',
            'content' => [
                [
                    'type' => 'web_search_result',
                    'title' => 'Quantum Computing Breakthroughs in 2025',
                    'url' => 'https://example.com',
                ],
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function messagesCompletionWithDocumentCitations(): array
{
    return [
        'id' => 'msg_019hiOHAEXQwq1PTeETNEBWe',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-4-6',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 1024,
            'output_tokens' => 120,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ],
        'content' => [
            [
                'type' => 'text',
                'text' => 'According to the document, ',
            ],
            [
                'type' => 'text',
                'text' => 'the grass is green',
                'citations' => [
                    [
                        'type' => 'char_location',
                        'cited_text' => 'The grass is green.',
                        'document_index' => 0,
                        'document_title' => 'Example Document',
                        'start_char_index' => 0,
                        'end_char_index' => 20,
                    ],
                ],
            ],
            [
                'type' => 'text',
                'text' => ' and ',
            ],
            [
                'type' => 'text',
                'text' => 'the sky is blue',
                'citations' => [
                    [
                        'type' => 'char_location',
                        'cited_text' => 'The sky is blue.',
                        'document_index' => 0,
                        'document_title' => 'Example Document',
                        'start_char_index' => 20,
                        'end_char_index' => 36,
                    ],
                ],
            ],
            [
                'type' => 'text',
                'text' => '. Information from page 5 states that ',
            ],
            [
                'type' => 'text',
                'text' => 'water is essential',
                'citations' => [
                    [
                        'type' => 'page_location',
                        'cited_text' => 'Water is essential for life.',
                        'document_index' => 1,
                        'document_title' => 'PDF Document',
                        'start_page_number' => 5,
                        'end_page_number' => 6,
                    ],
                ],
            ],
            [
                'type' => 'text',
                'text' => '. The custom document mentions ',
            ],
            [
                'type' => 'text',
                'text' => 'important findings',
                'citations' => [
                    [
                        'type' => 'content_block_location',
                        'cited_text' => 'These are important findings.',
                        'document_index' => 2,
                        'document_title' => 'Custom Content Document',
                        'start_block_index' => 0,
                        'end_block_index' => 1,
                    ],
                ],
            ],
        ],
        'stop_reason' => 'end_turn',
    ];
}

function messagesCompletionStreamCitationsDeltaChunk(): array
{
    return [
        'type' => 'content_block_delta',
        'index' => 0,
        'delta' => [
            'type' => 'citations_delta',
            'citation' => [
                'type' => 'char_location',
                'cited_text' => 'The grass is green.',
                'document_index' => 0,
                'document_title' => 'Example Document',
                'start_char_index' => 0,
                'end_char_index' => 20,
            ],
        ],
    ];
}

/**
 * @return array<string, int>
 */
function messagesCountTokens(): array
{
    return [
        'input_tokens' => 2095,
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
            'model' => 'claude-haiku-4-5',
            'stop_reason' => null,
            'stop_sequence' => null,
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 1,
            ],
        ],
    ];
}

function messagesCompletionStreamFirstChunkWithCache(): array
{
    return [
        'type' => 'message_start',
        'message' => [
            'id' => 'msg_01YS82gyNJHzAN1xVt2ymmTN',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [],
            'model' => 'claude-haiku-4-5',
            'stop_reason' => null,
            'stop_sequence' => null,
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 1,
                'cache_creation_input_tokens' => 30,
                'cache_read_input_tokens' => 40,
            ],
        ],
    ];
}

function messagesCompletionStreamLastChunkWithCache(): array
{
    return [
        'type' => 'message_delta',
        'delta' => [
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
        ],
        'usage' => [
            'output_tokens' => 15,
            'cache_creation_input_tokens' => 30,
            'cache_read_input_tokens' => 40,
        ],
    ];
}

function messagesCompletionStreamFirstChunkWithExtendedUsage(): array
{
    return [
        'type' => 'message_start',
        'message' => [
            'id' => 'msg_01YS82gyNJHzAN1xVt2ymmTN',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [],
            'model' => 'claude-sonnet-4-6',
            'stop_reason' => null,
            'stop_sequence' => null,
            'usage' => [
                'input_tokens' => 2048,
                'output_tokens' => 1,
                'cache_creation_input_tokens' => 248,
                'cache_read_input_tokens' => 1800,
                'cache_creation' => [
                    'ephemeral_5m_input_tokens' => 148,
                    'ephemeral_1h_input_tokens' => 100,
                ],
                'service_tier' => 'standard',
                'server_tool_use' => [
                    'web_search_requests' => 3,
                ],
            ],
        ],
    ];
}

function messagesCompletionStreamLastChunkWithExtendedUsage(): array
{
    return [
        'type' => 'message_delta',
        'delta' => [
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
        ],
        'usage' => [
            'output_tokens' => 15,
            'service_tier' => 'standard',
            'server_tool_use' => [
                'web_search_requests' => 3,
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

function messagesCompletionStreamThinkingContentBlockStartChunk(): array
{
    return [
        'type' => 'content_block_start',
        'index' => 0,
        'content_block' => [
            'type' => 'thinking',
            'thinking' => '',
        ],
    ];
}

function messagesCompletionStreamThinkingDeltaChunk(): array
{
    return [
        'type' => 'content_block_delta',
        'index' => 0,
        'delta' => [
            'type' => 'thinking_delta',
            'thinking' => 'I need to find the GCD using the Euclidean algorithm.',
        ],
    ];
}

function messagesCompletionStreamSignatureDeltaChunk(): array
{
    return [
        'type' => 'content_block_delta',
        'index' => 0,
        'delta' => [
            'type' => 'signature_delta',
            'signature' => 'EqQBCgIYAhIM1gbcDa9GJwZA2b3h',
        ],
    ];
}

function messagesCompletionStreamContentBlockStopChunk(): array
{
    return [
        'type' => 'content_block_stop',
        'index' => 0,
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

/**
 * @return resource
 */
function messagesCompletionStreamWithAdaptiveThinking()
{
    return fopen(__DIR__.'/Streams/MessagesCompletionCreateWithAdaptiveThinking.txt', 'r');
}
