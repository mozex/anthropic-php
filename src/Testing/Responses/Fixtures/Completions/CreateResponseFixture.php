<?php

namespace Anthropic\Testing\Responses\Fixtures\Completions;

final class CreateResponseFixture
{
    public const ATTRIBUTES = [
        'type' => 'completion',
        'id' => 'compl_01Sb5nmX365bQaWJ3jDfSgqB',
        'completion' => ' Hello!',
        'stop_reason' => 'stop_sequence',
        'model' => 'claude-2.1',
        'stop' => '\n\nHuman:',
        'log_id' => 'compl_01Sb5nmX365bQaWJ3jDfSgqB',
    ];
}
