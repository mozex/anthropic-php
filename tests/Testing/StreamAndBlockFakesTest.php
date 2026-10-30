<?php

declare(strict_types=1);

use Anthropic\Responses\Batches\BatchIndividualResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Blocks;
use Anthropic\Testing\ClientFake;

/**
 * The text, thinking and tool input a stream's deltas carry, joined per block.
 *
 * @return array<int, string>
 */
function streamedDeltas(iterable $stream): array
{
    $joined = [];

    foreach ($stream as $event) {
        if ($event->type === 'content_block_delta') {
            $joined[$event->index] = ($joined[$event->index] ?? '').($event->delta->text ?? $event->delta->thinking ?? $event->delta->partial_json ?? '');
        }
    }

    return $joined;
}

test('a stream fakes from a list of events, with the fakes\' meta or headers of its own', function () {
    // the text-only stream of https://platform.claude.com/docs/en/build-with-claude/streaming, shortened
    $events = [
        ['type' => 'message_start', 'message' => ['id' => 'msg_014p7gG3wDgGV9EUtLvnow3U', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5', 'stop_sequence' => null, 'usage' => ['input_tokens' => 472, 'output_tokens' => 2], 'content' => [], 'stop_reason' => null]],
        ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Okay']],
        ['type' => 'content_block_stop', 'index' => 0],
        ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => 15]],
        ['type' => 'message_stop'],
    ];

    $stream = CreateStreamedResponse::fake($events);

    expect(array_map(fn ($event): string => $event->type, iterator_to_array($stream, false)))->toBe(['message_start', 'content_block_start', 'content_block_delta', 'content_block_stop', 'message_delta'])
        ->and(CreateStreamedResponse::fake($events)->meta()->requestId)->toBe('02c10373f63cf2954851197d75c0adab')
        ->and(CreateStreamedResponse::fake($events, ['request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'])->meta()->requestId)->toBe('req_018EeWyXxfu5pfWkrYcMdjWG');
});

test('a stream fakes from a CreateResponse, block by block, as the API streams it', function () {
    $response = CreateResponse::fake([
        'content' => [
            Blocks::thinking('Let me check the weather.'),
            Blocks::text('I\'ll look it up.'),
            Blocks::toolUse('get_weather', ['location' => 'San Francisco, CA']),
        ],
        'stop_reason' => 'tool_use',
    ]);

    $events = iterator_to_array(CreateStreamedResponse::fake($response), false);
    $types = array_map(fn ($event): string => $event->type.($event->type === 'content_block_delta' ? ':'.$event->delta->type : ''), $events);

    expect($types)->toBe([
        'message_start',
        'content_block_start', 'content_block_delta:thinking_delta', 'content_block_delta:signature_delta', 'content_block_stop',
        'content_block_start', 'content_block_delta:text_delta', 'content_block_stop',
        'content_block_start', 'content_block_delta:input_json_delta', 'content_block_stop',
        'message_delta',
    ])
        ->and(streamedDeltas(CreateStreamedResponse::fake($response)))->toBe([
            0 => 'Let me check the weather.',
            1 => 'I\'ll look it up.',
            2 => '{"location":"San Francisco, CA"}',
        ])
        ->and($events[3]->delta->signature)->toBe('EqQBCgIYAhIM1gbcDa9GJwZA2b3hGgxBdjrkzLoky3dl1pkiMOYds')
        ->and($events[0]->message->content)->toBe([])
        ->and($events[8]->content_block_start->name)->toBe('get_weather')
        ->and($events[11]->delta->stop_reason)->toBe('tool_use');
});

test('the late fields and the cumulative usage come on message_delta, as the API sends them', function () {
    // composed: a refusal's stop_details and a usage with cache, server tool and iteration counts, in the shapes of
    // https://platform.claude.com/docs/en/build-with-claude/streaming; the container of its code execution stream, the
    // applied edits of https://platform.claude.com/docs/en/build-with-claude/context-editing
    $edits = ['applied_edits' => [['type' => 'clear_thinking_20251015', 'cleared_thinking_turns' => 3, 'cleared_input_tokens' => 15000]]];
    $response = CreateResponse::fake([
        'stop_reason' => 'refusal',
        'stop_details' => ['type' => 'refusal', 'category' => 'cyber', 'explanation' => 'This request was declined.'],
        'container' => ['id' => 'container_xyz789', 'expires_at' => '2026-01-20T14:30:00Z'],
        'context_management' => $edits,
        'usage' => [
            'input_tokens' => 10682, 'output_tokens' => 510, 'cache_read_input_tokens' => 100, 'service_tier' => 'standard',
            'server_tool_use' => ['web_search_requests' => 1],
            'iterations' => [['type' => 'message', 'input_tokens' => 10682, 'output_tokens' => 510]],
        ],
    ]);

    $events = iterator_to_array(CreateStreamedResponse::fake($response), false);
    $start = $events[0]->toArray();
    $delta = end($events)->toArray();

    expect($start['message'])->not->toHaveKeys(['stop_details', 'container', 'context_management'])
        ->and($start['usage'])->not->toHaveKeys(['server_tool_use', 'iterations'])
        ->and($start['usage']['output_tokens'])->toBe(1)
        ->and($delta['delta']['stop_reason'])->toBe('refusal')
        ->and($delta['delta']['stop_details']['category'])->toBe('cyber')
        ->and($delta['delta']['container']['id'])->toBe('container_xyz789')
        ->and($delta['context_management'])->toBe($edits)
        ->and($delta['usage']['cache_read_input_tokens'])->toBe(100)
        ->and($delta['usage']['server_tool_use'])->toBe(['web_search_requests' => 1])
        ->and($delta['usage']['iterations'][0]['output_tokens'])->toBe(510)
        ->and($delta['usage'])->not->toHaveKey('service_tier');
});

test('citations stream as a citations_delta each, and a threshold compaction as one compaction_delta', function () {
    // the citation of https://platform.claude.com/docs/en/build-with-claude/citations; composed: a compaction block
    $citation = ['type' => 'char_location', 'cited_text' => 'The grass is green.', 'document_index' => 0, 'document_title' => 'Example Document', 'start_char_index' => 0, 'end_char_index' => 20];
    $events = iterator_to_array(CreateStreamedResponse::fake(CreateResponse::fake(['content' => [
        ['type' => 'compaction', 'content' => 'Summary of the conversation: the user is designing a recipe app.'],
        Blocks::text('the grass is green', [$citation]),
    ]])), false);

    $deltas = array_values(array_filter($events, fn ($event): bool => $event->type === 'content_block_delta'));

    expect($deltas[0]->toArray()['delta'])->toMatchArray(['type' => 'compaction_delta', 'content' => 'Summary of the conversation: the user is designing a recipe app.'])
        ->and($deltas[2]->toArray()['delta']['type'])->toBe('citations_delta')
        ->and($deltas[2]->toArray()['delta']['citation']['cited_text'])->toBe('The grass is green.');
});

test('a signed compaction opens whole, and thinking with no signature sends no signature_delta', function () {
    // the compaction block of https://platform.claude.com/docs/en/build-with-claude/compaction-on-demand; composed: an
    // unsigned thinking block
    $events = iterator_to_array(CreateStreamedResponse::fake(CreateResponse::fake(['content' => [
        ['type' => 'compaction', 'content' => 'Summary of the conversation: the user is designing the data model for a recipe app.', 'signature' => 'EuYBCkQY'],
        ['type' => 'thinking', 'thinking' => 'Let me analyze this step by step...'],
    ]])), false);

    $deltas = array_map(fn ($event): array => $event->toArray()['delta'], array_values(array_filter($events, fn ($event): bool => $event->type === 'content_block_delta')));

    expect($events[1]->toArray()['content_block_start']['signature'] ?? null)->toBe('EuYBCkQY')
        ->and($deltas)->toHaveCount(1)
        ->and($deltas[0]['type'])->toBe('thinking_delta');
});

test('a stream of a CreateResponse keeps its meta', function () {
    $response = CreateResponse::from(CreateResponse::fake()->toArray(), MetaInformation::from(['request-id' => ['req_018EeWyXxfu5pfWkrYcMdjWG']]));

    expect(CreateStreamedResponse::fake($response)->meta()->requestId)->toBe('req_018EeWyXxfu5pfWkrYcMdjWG');
});

test('a block with no delta type streams whole in its start event', function () {
    $events = iterator_to_array(CreateStreamedResponse::fake(CreateResponse::fake(['content' => [Blocks::serverToolUse(), Blocks::webSearchToolResult(), Blocks::text('Sunny.')]])), false);

    expect($events[4]->content_block_start->type)->toBe('web_search_tool_result')
        ->and($events[4]->toArray()['content_block_start']['content'][0]['url'])->toBe('https://world-weather.info/forecast/usa/new_york/may-2025/')
        ->and($events[5]->type)->toBe('content_block_stop');
});

test('the block builders make blocks a response accepts, with the docs\' IDs', function () {
    $response = CreateResponse::from([
        ...CreateResponse::fake()->toArray(),
        'content' => [
            Blocks::thinking('Thinking it over.'),
            Blocks::serverToolUse(['query' => 'weather NYC today']),
            Blocks::webSearchToolResult(),
            Blocks::toolUse('get_weather', ['location' => 'Paris']),
            Blocks::text('Done.'),
        ],
    ], CreateResponse::fakeResponseMetaInformation());

    expect(array_map(fn ($block): string => $block->type, $response->content))->toBe(['thinking', 'server_tool_use', 'web_search_tool_result', 'tool_use', 'text'])
        ->and($response->content[1]->id)->toBe('srvtoolu_014hJH82Qum7Td6UV8gDXThB')
        ->and($response->content[3]->id)->toBe('toolu_01T1x1fJ34qAmk2tNTrN7Up6')
        ->and($response->content[3]->input)->toBe(['location' => 'Paris'])
        ->and(Blocks::text('Paris', [['type' => 'char_location', 'cited_text' => 'Paris', 'document_index' => 0, 'document_title' => null, 'start_char_index' => 0, 'end_char_index' => 5]])['citations'])->toHaveCount(1);
});

test('batch results fake from a list of result arrays', function () {
    $results = BatchResultResponse::fake([
        // the result lines of https://platform.claude.com/docs/en/build-with-claude/batch-processing; composed: the
        // second result's envelope
        ['custom_id' => 'my-second-request', 'result' => ['type' => 'succeeded', 'message' => CreateResponse::fake()->toArray()]],
        ['custom_id' => 'my-fifth-request', 'result' => ['type' => 'canceled']],
    ]);

    $lines = iterator_to_array($results, false);

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBeInstanceOf(BatchIndividualResponse::class)
        ->and($lines[0]->customId)->toBe('my-second-request')
        ->and($lines[0]->result->message)->toBeInstanceOf(CreateResponse::class)
        ->and($lines[1]->result->type)->toBe('canceled')
        ->and(BatchResultResponse::fake([])->meta()->requestId)->toBe('02c10373f63cf2954851197d75c0adab');
});

test('a stream fake answers a queued streamed call', function () {
    $fake = new ClientFake([CreateStreamedResponse::fake(CreateResponse::fake(['content' => [Blocks::text('Hello!')]]))]);

    expect(streamedDeltas($fake->messages()->createStreamed(['model' => 'claude-opus-5-5'])))->toBe([0 => 'Hello!']);
});
