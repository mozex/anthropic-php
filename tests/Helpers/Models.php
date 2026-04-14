<?php

/**
 * @return array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int, max_tokens: int, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}}
 */
function modelRetrieve(): array
{
    return [
        'id' => 'claude-sonnet-4-6',
        'type' => 'model',
        'created_at' => '2025-05-14T00:00:00Z',
        'display_name' => 'Claude Sonnet 4.6',
        'max_input_tokens' => 200000,
        'max_tokens' => 64000,
        'capabilities' => [
            'batch' => ['supported' => true],
            'citations' => ['supported' => true],
            'code_execution' => ['supported' => true],
            'context_management' => [
                'supported' => true,
                'clear_thinking_20251015' => ['supported' => true],
                'clear_tool_uses_20250919' => ['supported' => true],
                'compact_20260112' => ['supported' => true],
            ],
            'effort' => [
                'supported' => true,
                'low' => ['supported' => true],
                'medium' => ['supported' => true],
                'high' => ['supported' => true],
                'max' => ['supported' => true],
            ],
            'image_input' => ['supported' => true],
            'pdf_input' => ['supported' => true],
            'structured_outputs' => ['supported' => true],
            'thinking' => [
                'supported' => true,
                'types' => [
                    'adaptive' => ['supported' => true],
                    'enabled' => ['supported' => true],
                ],
            ],
        ],
    ];
}

/**
 * @return array{data: array<int, array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int, max_tokens: int, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}}>, first_id: string, last_id: string, has_more: bool}
 */
function modelList(): array
{
    return [
        'data' => [
            modelRetrieve(),
            [
                'id' => 'claude-haiku-4-5',
                'type' => 'model',
                'created_at' => '2025-10-01T00:00:00Z',
                'display_name' => 'Claude Haiku 4.5',
                'max_input_tokens' => 200000,
                'max_tokens' => 64000,
                'capabilities' => [
                    'batch' => ['supported' => true],
                    'citations' => ['supported' => true],
                    'code_execution' => ['supported' => true],
                    'context_management' => [
                        'supported' => true,
                        'clear_thinking_20251015' => ['supported' => true],
                        'clear_tool_uses_20250919' => ['supported' => true],
                        'compact_20260112' => ['supported' => true],
                    ],
                    'effort' => [
                        'supported' => true,
                        'low' => ['supported' => true],
                        'medium' => ['supported' => true],
                        'high' => ['supported' => true],
                        'max' => ['supported' => true],
                    ],
                    'image_input' => ['supported' => true],
                    'pdf_input' => ['supported' => true],
                    'structured_outputs' => ['supported' => true],
                    'thinking' => [
                        'supported' => true,
                        'types' => [
                            'adaptive' => ['supported' => true],
                            'enabled' => ['supported' => true],
                        ],
                    ],
                ],
            ],
        ],
        'first_id' => 'claude-sonnet-4-6',
        'last_id' => 'claude-haiku-4-5',
        'has_more' => true,
    ];
}
