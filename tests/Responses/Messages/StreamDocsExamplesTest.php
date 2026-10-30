<?php

declare(strict_types=1);

use Anthropic\Responses\Messages\CreateResponseContentCaller;
use Anthropic\Responses\Messages\CreateResponseContentFallbackTrigger;
use Anthropic\Responses\Messages\CreateResponseStopDetails;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\StreamResponse;
use GuzzleHttp\Psr7\Response;

/**
 * @return array<int, CreateStreamedResponse>
 */
function messagesStreamDocsExampleEvents(string $name): array
{
    $stream = new StreamResponse(CreateStreamedResponse::class, new Response(200, [], messagesStreamDocsExamples()[$name]));

    return iterator_to_array($stream, false);
}

/**
 * The payloads of the events a stream yields, decoded as the client does: up to `message_stop`, without pings.
 *
 * @return array<int, array<string, mixed>>
 */
function messagesStreamDocsExamplePayloads(string $name): array
{
    preg_match_all('/^data: (.+)$/m', messagesStreamDocsExamples()[$name], $matches);

    $payloads = [];

    foreach ($matches[1] as $data) {
        $payload = json_decode($data, true, flags: JSON_THROW_ON_ERROR);

        if ($payload['type'] === 'message_stop') {
            break;
        }

        if ($payload['type'] !== 'ping') {
            $payloads[] = $payload;
        }
    }

    return $payloads;
}

/**
 * Narrows an event's `toArray()` to the keys of the payload it was built from, at every depth, in the
 * payload's order, so comparing the two shows whether every value the API sent came back unchanged.
 */
function streamedEventAsSent(mixed $output, mixed $payload): mixed
{
    if (! is_array($output) || ! is_array($payload)) {
        return $output;
    }

    if (array_is_list($payload)) {
        return array_map(fn (int $index): mixed => streamedEventAsSent($output[$index], $payload[$index] ?? null), array_keys($output));
    }

    $narrowed = [];

    foreach ($payload as $key => $value) {
        if (array_key_exists($key, $output)) {
            $narrowed[$key] = streamedEventAsSent($output[$key], $value);
        }
    }

    return $narrowed;
}

/**
 * @param  array<string, mixed>  $payload
 */
function expectStreamedEventKeeps(CreateStreamedResponse $event, array $payload): void
{
    $output = $event->toArray();

    // the block a content_block_start carries comes back as content_block_start
    if (array_key_exists('content_block', $payload)) {
        $output['content_block'] = $output['content_block_start'];
    }

    // message_start's usage comes back once, as the event's usage
    if (isset($payload['message']['usage']) && ! array_key_exists('usage', $payload)) {
        $output['message']['usage'] = $output['usage'];
    }

    expect(streamedEventAsSent($output, $payload))->toBe($payload);
}

test('docs streams keep every field the API sent', function (string $name) {
    $events = messagesStreamDocsExampleEvents($name);
    $payloads = messagesStreamDocsExamplePayloads($name);

    expect($events)->toHaveCount(count($payloads));

    foreach ($events as $index => $event) {
        expectStreamedEventKeeps($event, $payloads[$index]);
    }
})->with(fn (): array => array_keys(messagesStreamDocsExamples()));

test('a compaction block that arrives whole in content_block_start streams', function () {
    $client = mockStreamClient('POST', 'messages', [
        'model' => 'claude-opus-5-5',
        'max_tokens' => 4096,
        'messages' => [['role' => 'user', 'content' => 'Good. Now suggest field names for Recipe.']],
        'compaction' => ['type' => 'summarize'],
        'stream' => true,
    ], new Response(200, [], messagesStreamDocsExamples()['on-demand compaction']));

    $events = iterator_to_array($client->messages()->createStreamed([
        'model' => 'claude-opus-5-5',
        'max_tokens' => 4096,
        'messages' => [['role' => 'user', 'content' => 'Good. Now suggest field names for Recipe.']],
        'compaction' => ['type' => 'summarize'],
    ]), false);

    expect($events)->toHaveCount(4)
        ->and($events[1]->content_block_start)
        ->type->toBe('compaction')
        ->content->toBe('Summary of the conversation: the user is designing the data model for a recipe app. The entities agreed so far are Recipe, Ingredient, Step, and RecipeIngredient, which holds the quantity and unit. The user then asked for field names for Recipe.')
        ->signature->toBe('EuYBCkQY')
        ->encrypted_content->toBeNull()
        ->and($events[3]->delta->stop_reason)->toBe('compaction');
});

test('a streamed refusal carries its stop details on message_delta', function () {
    $delta = messagesStreamDocsExampleEvents('refusal')[4];

    expect($delta->type)->toBe('message_delta')
        ->and($delta->delta->stop_reason)->toBe('refusal')
        ->and($delta->delta->stop_details)->toBeInstanceOf(CreateResponseStopDetails::class)
        ->and($delta->delta->stop_details)
        ->type->toBe('refusal')
        ->category->toBe('cyber')
        ->explanation->toBe('This request was declined because it could enable cyber harm.');
});

test('a message_delta without stop details reads them as null', function () {
    $events = messagesStreamDocsExampleEvents('server-side fallback');

    expect($events[2]->delta->stop_details)->toBeNull()
        ->and($events[2]->toArray()['delta'])->toHaveKey('stop_details', null)
        ->and(messagesStreamDocsExampleEvents('tool use')[24]->delta->stop_details)->toBeNull();
});

test('mcp connector block starts are typed', function () {
    [$listing, $use, , $result, $stringResult] = messagesStreamDocsExampleEvents('mcp connector');

    expect($listing->content_block_start)
        ->mcp_server_name->toBe('example-mcp')
        ->tools->toBe([['name' => 'echo', 'description' => 'Returns the text it receives.', 'input_schema' => ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text']]]])
        ->and($use->content_block_start)
        ->server_name->toBe('example-mcp')
        ->input->toBe([])
        ->and($result->content_block_start)
        ->is_error->toBeFalse()
        ->content->toBe([['type' => 'text', 'text' => 'Hello']])
        ->and($stringResult->content_block_start)
        ->is_error->toBeFalse()
        ->content->toBe('Hello');
});

test('tool call block starts carry their toolset name and caller', function () {
    $toolset = messagesStreamDocsExampleEvents('toolset')[0]->content_block_start;
    $programmatic = messagesStreamDocsExampleEvents('programmatic tool calling')[0]->content_block_start;

    expect($toolset)
        ->toolset_name->toBe('computer')
        ->name->toBe('left_click')
        ->caller->toBeNull()
        ->and($programmatic->caller)->toBeInstanceOf(CreateResponseContentCaller::class)
        ->and($programmatic->caller)
        ->type->toBe('code_execution_20260120')
        ->tool_id->toBe('srvtoolu_abc123')
        ->and($programmatic->toolset_name)->toBeNull();
});

test('fallback, thinking and advisor block starts are typed', function () {
    $fallback = messagesStreamDocsExampleEvents('server-side fallback')[0]->content_block_start;
    $thinking = messagesStreamDocsExampleEvents('thinking')[1]->content_block_start;
    $advisor = messagesStreamDocsExampleEvents('advisor')[1]->content_block_start;

    expect($fallback->trigger)->toBeInstanceOf(CreateResponseContentFallbackTrigger::class)
        ->and($fallback->trigger)
        ->type->toBe('refusal')
        ->category->toBe('cyber')
        ->and($thinking)
        ->signature->toBe('')
        ->thinking->toBe('')
        ->and($advisor)
        ->tool_use_id->toBe('srvtoolu_abc123')
        ->content->toBe(['type' => 'advisor_redacted_result', 'encrypted_content' => 'EqQBCkYIBRgCIiQ5ZjE0N2M2OC0yYWIxLTRkZTktYjA3ZC1hZTUyMzkxYjhkMmU']);
});

test('block starts type the fields no docs stream shows', function () {
    // composed: no docs example; the block fields from the beta reference
    $blocks = [
        ['type' => 'redacted_thinking', 'data' => 'EmwKAhgBEgy3va3pzix'],
        ['type' => 'container_upload', 'file_id' => 'file_011CNha8iCJcU1wXNR6q4V8w'],
        ['type' => 'text', 'text' => '', 'citations' => []],
        ['type' => 'compaction', 'content' => null, 'encrypted_content' => 'Eu8BCioIAxgCIiQ4', 'tool_changes' => [['type' => 'tool_addition', 'tool' => ['type' => 'tool_reference', 'tool_name' => 'get_weather']]]],
    ];

    $payloads = array_map(fn (array $block): array => ['type' => 'content_block_start', 'index' => 0, 'content_block' => $block], $blocks);
    $starts = array_map(CreateStreamedResponse::from(...), $payloads);

    expect($starts[0]->content_block_start->data)->toBe('EmwKAhgBEgy3va3pzix')
        ->and($starts[1]->content_block_start->file_id)->toBe('file_011CNha8iCJcU1wXNR6q4V8w')
        ->and($starts[2]->content_block_start->citations)->toBe([])
        ->and($starts[3]->content_block_start)
        ->content->toBeNull()
        ->encrypted_content->toBe('Eu8BCioIAxgCIiQ4')
        ->tool_changes->toBe([['type' => 'tool_addition', 'tool' => ['type' => 'tool_reference', 'tool_name' => 'get_weather']]]);

    foreach ($starts as $index => $start) {
        expectStreamedEventKeeps($start, $payloads[$index]);
    }
});

test('deltas type the compaction encrypted content and the thinking token estimate', function () {
    // composed: no docs example; the delta fields from the beta reference
    $compaction = ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'compaction_delta', 'content' => 'Summary of the conversation: The user requested help building a web scraper', 'encrypted_content' => 'Eu8BCioIAxgCIiQ4']];
    $thinking = ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => "\n462 = 3 × 147 + 21", 'estimated_tokens' => 12]];

    expect(CreateStreamedResponse::from($compaction)->delta->encrypted_content)->toBe('Eu8BCioIAxgCIiQ4')
        ->and(CreateStreamedResponse::from($thinking)->delta->estimated_tokens)->toBe(12);

    expectStreamedEventKeeps(CreateStreamedResponse::from($compaction), $compaction);
    expectStreamedEventKeeps(CreateStreamedResponse::from($thinking), $thinking);
});

test('message_delta carries the container, context management and input transformations', function () {
    $programmatic = messagesStreamDocsExampleEvents('programmatic tool calling')[1];
    $contextEditing = messagesStreamDocsExampleEvents('context editing')[0];
    $transformations = messagesStreamDocsExampleEvents('input transformations')[1];

    expect($programmatic->delta->container)->toBe(['id' => 'container_xyz789', 'expires_at' => '2026-01-20T14:30:00Z'])
        ->and($programmatic->context_management)->toBeNull()
        ->and($contextEditing->context_management)->toBe(['applied_edits' => [
            ['type' => 'clear_thinking_20251015', 'cleared_thinking_turns' => 3, 'cleared_input_tokens' => 15000],
            ['type' => 'clear_tool_uses_20250919', 'cleared_tool_uses' => 8, 'cleared_input_tokens' => 50000],
        ]])
        ->and($contextEditing->delta->container)->toBeNull()
        ->and($transformations->input_transformations)->toBe([
            ['type' => 'thinking_mismatch_allowed', 'path' => 'messages.1.content.0', 'reason' => 'prefix_binding_mismatch'],
        ]);
});

test('message_start carries the container, context management and input transformations', function () {
    $transformations = messagesStreamDocsExampleEvents('input transformations')[0];

    // composed: no docs example shows a container or context management on message_start
    $payload = ['type' => 'message_start', 'message' => [
        'id' => 'msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY',
        'type' => 'message',
        'role' => 'assistant',
        'content' => [],
        'model' => 'claude-opus-5-5',
        'stop_reason' => null,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 25, 'output_tokens' => 1],
        'container' => ['id' => 'container_xyz789', 'expires_at' => '2026-01-20T14:30:00Z', 'skills' => [['type' => 'anthropic', 'skill_id' => 'pdf', 'version' => 'latest']]],
        'context_management' => ['applied_edits' => []],
    ]];
    $start = CreateStreamedResponse::from($payload);

    expect($transformations->message->input_transformations)->toBe([
        ['type' => 'thinking_dropped', 'path' => 'messages.3.content.0', 'reason' => 'model_binding_mismatch'],
    ])
        ->and($transformations->message)
        ->container->toBeNull()
        ->context_management->toBeNull()
        ->and($start->message)
        ->container->toBe(['id' => 'container_xyz789', 'expires_at' => '2026-01-20T14:30:00Z', 'skills' => [['type' => 'anthropic', 'skill_id' => 'pdf', 'version' => 'latest']]])
        ->context_management->toBe(['applied_edits' => []])
        ->input_transformations->toBeNull();

    expectStreamedEventKeeps($start, $payload);
});

test('new fields reach toArray() only when the payload has them', function () {
    $toolUse = messagesStreamDocsExampleEvents('tool use');

    expect($toolUse[0]->toArray())->not->toHaveKeys(['context_management', 'input_transformations'])
        ->and($toolUse[0]->toArray()['message'])->not->toHaveKeys(['container', 'context_management', 'input_transformations'])
        ->and(array_keys($toolUse[16]->toArray()['content_block_start']))->toBe(['type', 'id', 'name', 'input', 'text', 'thinking'])
        ->and($toolUse[24]->toArray()['delta'])->not->toHaveKeys(['stop_details', 'container', 'encrypted_content', 'estimated_tokens']);
});

test('an unknown block start and an unknown delta keep their fields', function () {
    // composed: no docs example
    $start = ['type' => 'content_block_start', 'index' => 4, 'content_block' => ['type' => 'future_block', 'summary' => 'Kept as sent', 'parts' => [['id' => 1]], 'flag' => null]];
    $delta = ['type' => 'content_block_delta', 'index' => 4, 'delta' => ['type' => 'future_delta', 'value' => 'more', 'count' => 2]];

    expect(CreateStreamedResponse::from($start)->content_block_start->type)->toBe('future_block')
        ->and(CreateStreamedResponse::from($delta)->delta->type)->toBe('future_delta');

    expectStreamedEventKeeps(CreateStreamedResponse::from($start), $start);
    expectStreamedEventKeeps(CreateStreamedResponse::from($delta), $delta);
});
