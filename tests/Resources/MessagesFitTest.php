<?php

declare(strict_types=1);

use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Resources\Messages;
use Anthropic\Responses\Messages\CountTokensResponse;
use Anthropic\Testing\ClientFake;
use Anthropic\Testing\Requests\TestRequest;
use Anthropic\ValueObjects\Transporter\Response;

/**
 * A fake that counts a thousand input tokens per message, recording each count.
 */
function countingFake(): ClientFake
{
    return (new ClientFake)->respondTo([Messages::class, 'countTokens'], fn (TestRequest $request): CountTokensResponse => CountTokensResponse::fake([
        // composed: a count that grows with the conversation, in the shape of the count_tokens reference's example
        'input_tokens' => 1000 * count($request->args()[0]['messages']),
    ]));
}

/**
 * A conversation with a tool call in the middle: the cuts can fall before turns 4 and 6 (user turns that answer no tool
 * call), never before turn 2, which carries the tool's result.
 *
 * @return list<array<string, mixed>>
 */
function trimmableConversation(): array
{
    // the get_weather call and result of https://platform.claude.com/docs/en/agents-and-tools/tool-use/handle-tool-calls;
    // composed: the text turns around them
    return [
        ['role' => 'user', 'content' => 'What is the weather like in San Francisco?'],
        ['role' => 'assistant', 'content' => [['type' => 'tool_use', 'id' => 'toolu_01A09q90qw90lq917835lq9', 'name' => 'get_weather', 'input' => ['location' => 'San Francisco, CA', 'unit' => 'celsius']]]],
        ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_01A09q90qw90lq917835lq9', 'content' => '15 degrees']]],
        ['role' => 'assistant', 'content' => 'It is 15 degrees in San Francisco.'],
        ['role' => 'user', 'content' => 'And tomorrow?'],
        ['role' => 'assistant', 'content' => 'I can only check the current weather.'],
        ['role' => 'user', 'content' => 'Then what should I wear today?'],
    ];
}

test('fits() counts only what count_tokens takes and compares the input tokens with the limit', function () {
    $client = mockClient('POST', 'messages/count_tokens', [
        'model' => 'claude-sonnet-4-6',
        'messages' => [['role' => 'user', 'content' => 'Hello!']],
    ], Response::from(messagesCountTokens(), metaHeaders()));

    expect($client->messages()->fits([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 1024,
        'temperature' => 0.5,
        'stream' => true,
        'messages' => [['role' => 'user', 'content' => 'Hello!']],
    ], 2095))->toBeTrue();
});

test('fits() is false over the limit', function () {
    $fake = new ClientFake([CountTokensResponse::fake(['input_tokens' => 2095])]);

    expect($fake->messages()->fits(['model' => 'claude-sonnet-4-6', 'messages' => [['role' => 'user', 'content' => 'Hello!']]], 2094))->toBeFalse();
});

test('trimToFit() drops the oldest turns down to the first cut that fits, keeping the system prompt and the tools', function () {
    $fake = countingFake();
    $parameters = ['model' => 'claude-sonnet-4-6', 'max_tokens' => 1024, 'system' => 'You are a weather assistant.', 'tools' => [['name' => 'get_weather']], 'messages' => trimmableConversation()];

    // 7 turns count 7,000; from turn 4, 3,000; from turn 6, 1,000
    $trimmed = $fake->messages()->trimToFit($parameters, 3500);

    expect($trimmed['messages'])->toBe(array_slice(trimmableConversation(), 4))
        ->and($trimmed['system'])->toBe('You are a weather assistant.')
        ->and($trimmed['tools'])->toBe([['name' => 'get_weather']])
        ->and($trimmed['max_tokens'])->toBe(1024);

    // the full request, the last cut, then one halving, none of them with max_tokens
    $fake->assertSentTimes(Messages::class, 3, fn (string $method, array $parameters): bool => ! array_key_exists('max_tokens', $parameters));
});

test('trimToFit() halves past a cut that doesn\'t fit to a later one that does', function () {
    $fake = countingFake();
    // composed: two more text turns, so the cuts are 4, 6 and 8 and the first halving (turn 6) is over the limit
    $messages = [...trimmableConversation(), ['role' => 'assistant', 'content' => 'A light jacket.'], ['role' => 'user', 'content' => 'Thanks!']];

    // from turn 6 is 3,000 tokens, from turn 8 is 1,000
    expect($fake->messages()->trimToFit(['model' => 'claude-sonnet-4-6', 'messages' => $messages], 2000)['messages'])->toBe(array_slice($messages, 8));

    // all 9 turns, the last cut (8), the middle (6), then nothing left to halve
    $fake->assertSentTimes(Messages::class, 3);
});

test('a user turn with a tool result beside its text is no cut, and betas reach the count', function () {
    $fake = countingFake();
    $messages = trimmableConversation();
    // composed: the tool result with the user's next question in the same turn
    $messages[2]['content'][] = ['type' => 'text', 'text' => 'And what should I wear?'];

    $trimmed = $fake->messages()->trimToFit(['model' => 'claude-sonnet-4-6', 'betas' => ['context-management-2025-06-27'], 'messages' => $messages], 5000);

    expect($trimmed['messages'][0])->toBe(['role' => 'user', 'content' => 'And tomorrow?']);
    $fake->assertSentTimes(Messages::class, 3, fn (string $method, array $parameters): bool => $parameters['betas'] === ['context-management-2025-06-27']);
});

test('trimToFit() cuts a conversation decoded as objects too', function () {
    $messages = json_decode((string) json_encode(trimmableConversation()));

    expect(countingFake()->messages()->trimToFit(['model' => 'claude-sonnet-4-6', 'messages' => $messages], 3500)['messages'][0]->content)->toBe('And tomorrow?');
});

test('trimToFit() needs the turns as an array', function () {
    countingFake()->messages()->trimToFit(['model' => 'claude-sonnet-4-6', 'messages' => 'Hello!'], 500);
})->throws(InvalidArgumentException::class, 'needs `messages` as an array');

test('trimToFit() never cuts before a tool result, so a call keeps its answer', function () {
    $fake = countingFake();

    // from turn 2 would be 5,000, but it carries a tool result: the first cut that fits is turn 4
    expect($fake->messages()->trimToFit(['model' => 'claude-sonnet-4-6', 'messages' => trimmableConversation()], 5000)['messages'][0])
        ->toBe(['role' => 'user', 'content' => 'And tomorrow?']);
});

test('trimToFit() returns what already fits with one count', function () {
    $fake = countingFake();
    $parameters = ['model' => 'claude-sonnet-4-6', 'messages' => trimmableConversation()];

    expect($fake->messages()->trimToFit($parameters, 7000))->toBe($parameters);

    $fake->assertSentTimes(Messages::class, 1);
});

test('trimToFit() throws when even the last turn it can start from is over the limit', function () {
    countingFake()->messages()->trimToFit(['model' => 'claude-sonnet-4-6', 'messages' => trimmableConversation()], 500);
})->throws(InvalidArgumentException::class, 'is 1000 input tokens, over the limit of 500');

test('trimToFit() throws for a single turn over the limit', function () {
    countingFake()->messages()->trimToFit(['model' => 'claude-sonnet-4-6', 'messages' => [['role' => 'user', 'content' => 'Hello!']]], 500);
})->throws(InvalidArgumentException::class, 'is 1000 input tokens');
