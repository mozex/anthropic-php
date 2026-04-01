<?php

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseContent;
use Anthropic\Responses\Messages\CreateResponseUsage;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $completion = CreateResponse::from(messagesCompletion(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->id->toBe('msg_019hiOHAEXQwq1PTeETNEBWe')
        ->type->toBe('message')
        ->role->toBe('assistant')
        ->model->toBe('claude-sonnet-4-6')
        ->stop_sequence->toBeNull()
        ->stop_reason->toBe('end_turn')
        ->content->toBeArray()->toHaveCount(1)
        ->content->each->toBeInstanceOf(CreateResponseContent::class)
        ->usage->toBeInstanceOf(CreateResponseUsage::class)
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('from tool calls response', function () {
    $completion = CreateResponse::from(messagesCompletionWithToolCalls(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->id->toBe('msg_019hiOHAEXQwq1PTeETNEBWe')
        ->type->toBe('message')
        ->role->toBe('assistant')
        ->model->toBe('claude-sonnet-4-6')
        ->stop_sequence->toBeNull()
        ->stop_reason->toBe('tool_use')
        ->content->toBeArray()->toHaveCount(2)
        ->content->each->toBeInstanceOf(CreateResponseContent::class)
        ->usage->toBeInstanceOf(CreateResponseUsage::class)
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('from thinking response', function () {
    $completion = CreateResponse::from(messagesCompletionWithThinking(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->id->toBe('msg_019hiOHAEXQwq1PTeETNEBWe')
        ->type->toBe('message')
        ->role->toBe('assistant')
        ->model->toBe('claude-sonnet-4-6')
        ->stop_reason->toBe('end_turn')
        ->content->toBeArray()->toHaveCount(3)
        ->content->each->toBeInstanceOf(CreateResponseContent::class);

    expect($completion->content[0])
        ->type->toBe('thinking')
        ->thinking->toBe('Let me analyze this step by step...')
        ->signature->toBe('WaUjzkypQ2mUEVM36O2Txu');

    expect($completion->content[1])
        ->type->toBe('redacted_thinking')
        ->data->toBe('EmwKAhgBEgy3va3pzix/LafPsn4a');

    expect($completion->content[2])
        ->type->toBe('text')
        ->text->toBe("Hello! I'm Claude, an AI assistant. How can I help you today?");
});

test('from omitted thinking response', function () {
    $completion = CreateResponse::from(messagesCompletionWithOmittedThinking(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->content->toBeArray()->toHaveCount(2);

    expect($completion->content[0])
        ->type->toBe('thinking')
        ->thinking->toBe('')
        ->signature->toBe('EosnCkYICxIMMb3LzNrMu');

    expect($completion->content[1])
        ->type->toBe('text')
        ->text->toBe('The answer is 12,231.');
});

test('to array from omitted thinking response', function () {
    $completion = CreateResponse::from(messagesCompletionWithOmittedThinking(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithOmittedThinking());
});

test('to array from thinking response', function () {
    $completion = CreateResponse::from(messagesCompletionWithThinking(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithThinking());
});

test('as array accessible', function () {
    $completion = CreateResponse::from(messagesCompletion(), meta());

    expect(isset($completion['id']))->toBeTrue();

    expect($completion['id'])->toBe('msg_019hiOHAEXQwq1PTeETNEBWe');
});

test('to array', function () {
    $completion = CreateResponse::from(messagesCompletion(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletion());
});

test('from web search response', function () {
    $completion = CreateResponse::from(messagesCompletionWithWebSearch(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->id->toBe('msg_a930390d3a')
        ->stop_reason->toBe('end_turn')
        ->content->toBeArray()->toHaveCount(4);

    expect($completion->content[0])
        ->type->toBe('text')
        ->text->toBe("I'll search for when Claude Shannon was born.");

    expect($completion->content[1])
        ->type->toBe('server_tool_use')
        ->id->toBe('srvtoolu_01WYG3ziw53XMcoyKL4XcZmE')
        ->name->toBe('web_search')
        ->input->toBe(['query' => 'claude shannon birth date']);

    expect($completion->content[2])
        ->type->toBe('web_search_tool_result')
        ->tool_use_id->toBe('srvtoolu_01WYG3ziw53XMcoyKL4XcZmE')
        ->content->toBeArray()->toHaveCount(1);

    expect($completion->content[2]->content[0])
        ->toBe([
            'type' => 'web_search_result',
            'url' => 'https://en.wikipedia.org/wiki/Claude_Shannon',
            'title' => 'Claude Shannon - Wikipedia',
            'encrypted_content' => 'EqgfCioIARgBIiQ3YTAwMjY1Mi1mZjM5LTQ1NGUtODgxNC1kNjNjNTk1ZWI3Y',
            'page_age' => 'April 30, 2025',
        ]);

    expect($completion->content[3])
        ->type->toBe('text')
        ->text->toBe('Claude Shannon was born on April 30, 1916, in Petoskey, Michigan')
        ->citations->toBeArray()->toHaveCount(1);

    expect($completion->usage->serverToolUse)
        ->webSearchRequests->toBe(1);
});

test('to array from web search response', function () {
    $completion = CreateResponse::from(messagesCompletionWithWebSearch(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithWebSearch());
});

test('from code execution response', function () {
    $completion = CreateResponse::from(messagesCompletionWithCodeExecution(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->stop_reason->toBe('end_turn')
        ->container->toBe(['id' => 'container_123', 'expires_at' => '2025-03-15T10:30:00Z'])
        ->content->toBeArray()->toHaveCount(3);

    expect($completion->content[0])
        ->type->toBe('server_tool_use')
        ->id->toBe('srvtoolu_01A2B3C4D5E6F7G8H9')
        ->name->toBe('code_execution');

    expect($completion->content[1])
        ->type->toBe('code_execution_tool_result')
        ->tool_use_id->toBe('srvtoolu_01A2B3C4D5E6F7G8H9')
        ->content->toBe([
            'type' => 'code_execution_result',
            'stdout' => 'Hello, World!',
            'stderr' => '',
            'return_code' => 0,
        ]);

    expect($completion->usage->serverToolUse)
        ->codeExecutionRequests->toBe(1);
});

test('to array from code execution response', function () {
    $completion = CreateResponse::from(messagesCompletionWithCodeExecution(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithCodeExecution());
});

test('fake', function () {
    $response = CreateResponse::fake();

    expect($response)
        ->id->toBe('msg_019hiOHAEXQwq1PTeETNEBWe');
});

test('fake with override', function () {
    $response = CreateResponse::fake([
        'id' => 'msg-111',
        'content' => [
            [
                'text' => 'Hi, there!',
            ],
        ],
    ]);

    expect($response)
        ->id->toBe('msg-111')
        ->and($response->content[0])
        ->text->toBe('Hi, there!')
        ->type->toBe('text');
});

test('fake with tool calls', function () {
    $response = CreateResponse::fake([
        'id' => 'msg-111',
        'content' => [
            [
                'text' => 'Hi, there!',
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
    ]);

    expect($response)
        ->id->toBe('msg-111')
        ->and($response->content[0])
        ->text->toBe('Hi, there!')
        ->type->toBe('text')
        ->and($response->content[1])
        ->type->toBe('tool_use')
        ->id->toBe('toolu_016udJr9epWhTNC8Ec1mnVQf')
        ->name->toBe('get_weather')
        ->input->toBe(['location' => 'San Francisco, CA']);
});
