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
        ->model->toBe('claude-3-opus-20240229')
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
        ->model->toBe('claude-3-opus-20240229')
        ->stop_sequence->toBeNull()
        ->stop_reason->toBe('tool_use')
        ->content->toBeArray()->toHaveCount(2)
        ->content->each->toBeInstanceOf(CreateResponseContent::class)
        ->usage->toBeInstanceOf(CreateResponseUsage::class)
        ->meta()->toBeInstanceOf(MetaInformation::class);
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
