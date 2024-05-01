<?php

/**
 * @return array<string, mixed>
 */
function completion(): array
{
    return [
        'type' => 'completion',
        'id' => 'compl_01Sb5nmX365bQaWJ3jDfSgqB',
        'completion' => ' Hello!',
        'stop_reason' => 'stop_sequence',
        'model' => 'claude-2.1',
        'stop' => '\n\nHuman:',
        'log_id' => 'compl_01Sb5nmX365bQaWJ3jDfSgqB',
    ];
}

/**
 * @return resource
 */
function completionStream()
{
    return fopen(__DIR__.'/Streams/CompletionCreate.txt', 'r');
}
