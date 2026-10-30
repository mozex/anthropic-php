<?php

declare(strict_types=1);

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseContentFallbackTrigger;

function messagesDocsExample(string $name): CreateResponse
{
    return CreateResponse::from(json_decode(messagesDocsExamples()[$name], true, flags: JSON_THROW_ON_ERROR), meta());
}

test('docs examples round-trip through toArray()', function (string $json) {
    $response = CreateResponse::from(json_decode($json, true, flags: JSON_THROW_ON_ERROR), meta());

    $expected = docsExampleWithAlwaysEmittedKeys(json_decode($json, flags: JSON_THROW_ON_ERROR));

    expect(docsExampleJson($response->toArray()))->toBe(docsExampleJson($expected));
})->with(fn (): array => messagesDocsExamples());

test('an unknown block and an unknown top-level key survive toArray()', function () {
    // composed: no docs example
    $attributes = [
        ...messagesCompletion(),
        'content' => [
            ['type' => 'text', 'text' => 'Hello', 'future_annotation' => ['kind' => 'note']],
            ['type' => 'future_block', 'summary' => 'Kept as sent', 'parts' => [['id' => 1]], 'flag' => null],
        ],
        'future_field' => ['nested' => ['value' => 1]],
    ];

    $response = CreateResponse::from($attributes, meta());

    expect($response->toArray())->toBe($attributes)
        ->and($response['future_field'])->toBe(['nested' => ['value' => 1]])
        ->and($response->content[1]->type)->toBe('future_block');
});

test('a parameterless tool call replays its input as a JSON object', function () {
    $response = CreateResponse::from([
        ...messagesCompletion(),
        'content' => [
            ['type' => 'tool_use', 'id' => 'toolu_017nJn3RgSCkTMwuZDb4uUov', 'name' => 'screenshot', 'toolset_name' => 'computer', 'input' => []],
            ['type' => 'server_tool_use', 'id' => 'srvtoolu_abc123', 'name' => 'advisor', 'input' => []],
            // composed: no docs example of a parameterless MCP call
            ['type' => 'mcp_tool_use', 'id' => 'mcptoolu_014Q35RayjACSWkSj4X2yov1', 'name' => 'echo', 'server_name' => 'example-mcp', 'input' => []],
        ],
        'stop_reason' => 'tool_use',
    ], meta());

    $content = $response->toArray()['content'];

    expect($response->content[0]->input)->toBe([])
        ->and($content[0]['input'])->toBeInstanceOf(ArrayObject::class)->toHaveCount(0)
        ->and(json_encode($content[0], JSON_THROW_ON_ERROR))->toBe('{"type":"tool_use","id":"toolu_017nJn3RgSCkTMwuZDb4uUov","name":"screenshot","toolset_name":"computer","input":{}}')
        ->and(json_encode($content[1], JSON_THROW_ON_ERROR))->toBe('{"type":"server_tool_use","id":"srvtoolu_abc123","name":"advisor","input":{}}')
        ->and(json_encode($content[2], JSON_THROW_ON_ERROR))->toBe('{"type":"mcp_tool_use","id":"mcptoolu_014Q35RayjACSWkSj4X2yov1","name":"echo","server_name":"example-mcp","input":{}}');
});

test('a parameterless tool call builds again from its own toArray()', function () {
    $response = messagesDocsExample('computer toolset');

    $rebuilt = CreateResponse::from($response->toArray(), meta());

    expect($rebuilt->content[2]->input)->toBe([])
        ->and(json_encode($rebuilt->toArray(), JSON_THROW_ON_ERROR))->toBe(json_encode($response->toArray(), JSON_THROW_ON_ERROR))
        ->and(CreateResponse::fake($response->toArray())->content[2]->input)->toBe([]);
});

test('compaction blocks keep their signature', function () {
    $block = messagesDocsExample('on-demand compaction')->content[0];

    expect($block)
        ->type->toBe('compaction')
        ->signature->toBe('EuYBCkQY')
        ->and($block->toArray())->toHaveKey('signature', 'EuYBCkQY');
});

test('compaction blocks keep their encrypted content and tool changes', function () {
    // composed: no docs example shows encrypted_content or tool_changes; shapes from the beta API reference
    $attributes = [
        'type' => 'compaction',
        'content' => null,
        'encrypted_content' => 'EqQBCkYIBRgC',
        'tool_changes' => [
            ['type' => 'tool_addition', 'tool' => ['type' => 'tool_reference', 'name' => 'get_weather']],
            ['type' => 'tool_removal', 'tool' => ['type' => 'mcp_toolset_reference', 'server_name' => 'example-mcp']],
        ],
    ];

    $response = CreateResponse::from([...messagesCompletion(), 'content' => [$attributes]], meta());

    expect($response->content[0])
        ->encrypted_content->toBe('EqQBCkYIBRgC')
        ->tool_changes->toBe($attributes['tool_changes'])
        ->signature->toBeNull()
        ->and($response->toArray()['content'][0])->toBe($attributes);
});

test('mcp connector blocks are typed', function () {
    [$listing, $use, $result] = messagesDocsExample('mcp connector blocks')->content;

    expect($listing)
        ->mcp_server_name->toBe('example-mcp')
        ->tools->toHaveCount(1)
        ->tools->{0}->toHaveKey('name', 'echo')
        ->and($use)
        ->id->toBe('mcptoolu_014Q35RayjACSWkSj4X2yov1')
        ->name->toBe('echo')
        ->server_name->toBe('example-mcp')
        ->input->toBe(['param1' => 'value1', 'param2' => 'value2'])
        ->and($result)
        ->tool_use_id->toBe('mcptoolu_014Q35RayjACSWkSj4X2yov1')
        ->is_error->toBeFalse()
        ->content->toBe([['type' => 'text', 'text' => 'Hello']]);
});

test('advisor results are typed', function () {
    $result = messagesDocsExample('advisor error')->content[1];

    expect($result)
        ->type->toBe('advisor_tool_result')
        ->tool_use_id->toBe('srvtoolu_abc123')
        ->content->toBe(['type' => 'advisor_tool_result_error', 'error_code' => 'overloaded']);
});

test('toolset tool calls carry their toolset name', function () {
    $content = messagesDocsExample('computer toolset')->content;

    expect($content[1])
        ->name->toBe('left_click')
        ->toolset_name->toBe('computer')
        ->and($content[0]->toolset_name)->toBeNull();
});

test('input transformations are typed', function () {
    $response = messagesDocsExample('input transformations, two dropped');

    expect($response->input_transformations)->toBe([
        ['type' => 'thinking_dropped', 'path' => 'messages.2.content.0', 'reason' => 'prefix_binding_mismatch'],
        ['type' => 'thinking_dropped', 'path' => 'messages.4.content.0', 'reason' => 'prefix_binding_mismatch'],
    ])
        ->and($response['input_transformations'])->toBe($response->input_transformations)
        ->and(CreateResponse::from(messagesCompletion(), meta())->input_transformations)->toBeNull();
});

test('fallback blocks carry their trigger', function () {
    $block = messagesDocsExample('server-side fallback with trigger')->content[0];

    expect($block->trigger)
        ->toBeInstanceOf(CreateResponseContentFallbackTrigger::class)
        ->type->toBe('refusal')
        ->category->toBe('cyber');
});

test('a response without stop_sequence reads it as null', function () {
    $response = messagesDocsExample('refusal');

    expect($response->stop_sequence)->toBeNull()
        ->and($response->toArray())->toHaveKey('stop_sequence', null);
});

test('refusals carry the fallback credit', function () {
    $response = messagesDocsExample('fallback credit');

    expect($response->stop_details)
        ->fallback_credit_token->toBe('QW50aHJvcGljL0NsYXVkZQ==')
        ->fallback_has_prefill_claim->toBeTrue()
        ->recommended_model->toBe('claude-opus-4-8')
        ->and($response->usage->fallbackCredit)->toBe(['status' => ['type' => 'redeemed']])
        ->and(messagesDocsExample('refusal')->usage->fallbackCredit)->toBeNull();
});

test('diagnostics sent as null stay in toArray()', function () {
    $response = messagesDocsExample('cache diagnostics not requested');

    expect($response->diagnostics)->toBeNull()
        ->and($response->toArray())->toHaveKey('diagnostics', null);
});

test('a container with skills round-trips', function () {
    // composed: the container id from the programmatic tool calling page, expires_at and skills from the beta API reference
    $container = [
        'id' => 'container_xyz789',
        'expires_at' => '2019-12-27T18:11:19.117Z',
        'skills' => [['skill_id' => 'pdf', 'type' => 'anthropic', 'version' => 'latest']],
    ];

    $response = CreateResponse::from([...messagesCompletion(), 'container' => $container], meta());

    expect($response->container)->toBe($container)
        ->and($response->toArray()['container'])->toBe($container);
});

test('server tool results keep their caller', function () {
    // composed: no docs example shows a result with caller; the caller is the programmatic tool calling page's
    $attributes = [
        'type' => 'web_search_tool_result',
        'tool_use_id' => 'srvtoolu_014hJH82Qum7Td6UV8gDXThB',
        'content' => [],
        'caller' => ['type' => 'code_execution_20260120', 'tool_id' => 'srvtoolu_abc123'],
    ];

    $response = CreateResponse::from([...messagesCompletion(), 'content' => [$attributes]], meta());

    expect($response->content[0]->caller?->tool_id)->toBe('srvtoolu_abc123')
        ->and($response->toArray()['content'][0])->toBe($attributes);
});
