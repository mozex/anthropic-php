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
