<?php

declare(strict_types=1);

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Testing\Enums\OverrideStrategy;

test('a faked content block of another type replaces the fixture block', function () {
    $response = CreateResponse::fake([
        'content' => [
            [
                'type' => 'tool_use',
                'id' => 'toolu_01A09q90qw90lq917835lq9',
                'name' => 'get_weather',
                'input' => ['location' => 'San Francisco, CA'],
            ],
        ],
    ]);

    expect($response->content[0])
        ->type->toBe('tool_use')
        ->name->toBe('get_weather')
        ->text->toBeNull();
});

test('a faked content block of the same type still merges into the fixture block', function () {
    $response = CreateResponse::fake([
        'content' => [
            ['type' => 'text', 'text' => 'Hi there'],
        ],
    ]);

    expect($response->content[0])
        ->type->toBe('text')
        ->text->toBe('Hi there');
});

test('merging keeps array_replace_recursive semantics apart from the type rule', function (array $original, array $override) {
    expect(OverrideStrategy::Merge->apply($original, $override))
        ->toBe(array_replace_recursive($original, $override));
})->with([
    'a nested field' => [['usage' => ['input_tokens' => 10, 'output_tokens' => 20]], ['usage' => ['output_tokens' => 5]]],
    'a shorter list keeps the tail' => [['content' => [['text' => 'a'], ['text' => 'b']]], ['content' => [['text' => 'c']]]],
    'a longer list appends' => [['content' => [['text' => 'a']]], ['content' => [['text' => 'b'], ['type' => 'text', 'text' => 'c']]]],
    'an item without a type merges' => [['content' => [['type' => 'text', 'text' => 'a']]], ['content' => [['text' => 'b']]]],
    'a scalar replaces an array' => [['container' => ['id' => 'container_1']], ['container' => null]],
    'an array replaces a scalar' => [['stop_sequence' => null], ['stop_sequence' => ['x']]],
    'a top-level type merges' => [['type' => 'message', 'id' => 'a'], ['type' => 'other']],
]);

test('a nested item whose type differs is replaced, not merged', function () {
    expect(OverrideStrategy::Merge->apply(
        ['result' => ['type' => 'succeeded', 'message' => ['id' => 'msg_1']]],
        ['result' => ['type' => 'errored', 'error' => ['type' => 'invalid_request_error']]],
    ))->toBe([
        'result' => ['type' => 'errored', 'error' => ['type' => 'invalid_request_error']],
    ]);
});

test('the replace strategy replaces top-level keys', function () {
    expect(OverrideStrategy::Replace->apply(
        ['usage' => ['input_tokens' => 10, 'output_tokens' => 20]],
        ['usage' => ['output_tokens' => 5]],
    ))->toBe(['usage' => ['output_tokens' => 5]]);
});
