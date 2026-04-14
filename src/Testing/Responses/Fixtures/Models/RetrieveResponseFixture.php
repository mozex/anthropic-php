<?php

namespace Anthropic\Testing\Responses\Fixtures\Models;

final class RetrieveResponseFixture
{
    public const ATTRIBUTES = [
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
