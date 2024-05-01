<?php

namespace Anthropic\Testing\Responses\Fixtures\Messages;

final class CreateResponseFixture
{
    public const ATTRIBUTES = [
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
