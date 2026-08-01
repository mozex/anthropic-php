<?php

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseContent;
use Anthropic\Responses\Messages\CreateResponseContentCaller;
use Anthropic\Responses\Messages\CreateResponseContentFallbackModel;
use Anthropic\Responses\Messages\CreateResponseDiagnostics;
use Anthropic\Responses\Messages\CreateResponseDiagnosticsCacheMissReason;
use Anthropic\Responses\Messages\CreateResponseStopDetails;
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
        ->stop_details->toBeNull()
        ->content->toBeArray()->toHaveCount(1)
        ->content->each->toBeInstanceOf(CreateResponseContent::class)
        ->usage->toBeInstanceOf(CreateResponseUsage::class)
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('from refusal response', function () {
    $completion = CreateResponse::from(messagesCompletionWithRefusal(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->stop_reason->toBe('refusal')
        ->stop_details->toBeInstanceOf(CreateResponseStopDetails::class);

    expect($completion->stop_details)
        ->type->toBe('refusal')
        ->category->toBe('cyber')
        ->explanation->toBe('This request was flagged for a cybersecurity policy violation.');
});

test('to array from refusal response', function () {
    $completion = CreateResponse::from(messagesCompletionWithRefusal(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithRefusal());
});

test('from tool calls response with caller', function () {
    $completion = CreateResponse::from(messagesCompletionWithToolCallsAndCaller(), meta());

    expect($completion->content[0])
        ->type->toBe('tool_use')
        ->caller->toBeInstanceOf(CreateResponseContentCaller::class);

    expect($completion->content[0]->caller)
        ->type->toBe('direct')
        ->tool_id->toBeNull();

    expect($completion->content[1])
        ->type->toBe('server_tool_use')
        ->caller->toBeInstanceOf(CreateResponseContentCaller::class);

    expect($completion->content[1]->caller)
        ->type->toBe('code_execution_20250825')
        ->tool_id->toBe('srvtoolu_parentCodeExec01');
});

test('to array from tool calls response with caller', function () {
    $completion = CreateResponse::from(messagesCompletionWithToolCallsAndCaller(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithToolCallsAndCaller());
});

test('from container upload response', function () {
    $completion = CreateResponse::from(messagesCompletionWithContainerUpload(), meta());

    expect($completion->content[0])
        ->type->toBe('container_upload')
        ->file_id->toBe('file_01ABCDefGhIjKlMnOpQrStUv');
});

test('to array from container upload response', function () {
    $completion = CreateResponse::from(messagesCompletionWithContainerUpload(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithContainerUpload());
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

test('from document citations response', function () {
    $completion = CreateResponse::from(messagesCompletionWithDocumentCitations(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->content->toBeArray()->toHaveCount(8);

    // Plain text block without citations
    expect($completion->content[0])
        ->type->toBe('text')
        ->text->toBe('According to the document, ')
        ->citations->toBeNull();

    // char_location citation
    expect($completion->content[1])
        ->type->toBe('text')
        ->text->toBe('the grass is green')
        ->citations->toBeArray()->toHaveCount(1);

    expect($completion->content[1]->citations[0])
        ->toBe([
            'type' => 'char_location',
            'cited_text' => 'The grass is green.',
            'document_index' => 0,
            'document_title' => 'Example Document',
            'start_char_index' => 0,
            'end_char_index' => 20,
        ]);

    // page_location citation
    expect($completion->content[5]->citations[0]['type'])
        ->toBe('page_location');

    expect($completion->content[5]->citations[0]['start_page_number'])
        ->toBe(5);

    // content_block_location citation
    expect($completion->content[7]->citations[0]['type'])
        ->toBe('content_block_location');

    expect($completion->content[7]->citations[0]['start_block_index'])
        ->toBe(0);
});

test('to array from document citations response', function () {
    $completion = CreateResponse::from(messagesCompletionWithDocumentCitations(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithDocumentCitations());
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

test('from fast mode response', function () {
    $completion = CreateResponse::from(messagesCompletionWithFastMode(), meta());

    expect($completion)
        ->model->toBe('claude-opus-5')
        ->usage->speed->toBe('fast');
});

test('to array from fast mode response', function () {
    $completion = CreateResponse::from(messagesCompletionWithFastMode(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithFastMode());
});

test('from fallback response', function () {
    $completion = CreateResponse::from(messagesCompletionWithFallback(), meta());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->model->toBe('claude-opus-4-8')
        ->stop_reason->toBe('end_turn')
        ->stop_details->toBeNull()
        ->content->toBeArray()->toHaveCount(2);

    expect($completion->content[0])
        ->type->toBe('fallback')
        ->from->toBeInstanceOf(CreateResponseContentFallbackModel::class)
        ->from->model->toBe('claude-fable-5')
        ->to->toBeInstanceOf(CreateResponseContentFallbackModel::class)
        ->to->model->toBe('claude-opus-4-8');

    expect($completion->content[1])
        ->type->toBe('text')
        ->text->toBe('Hi! How can I help you today?');

    expect($completion->usage->iterations)
        ->toBeArray()->toHaveCount(2);

    expect($completion->usage->iterations[0])
        ->type->toBe('message')
        ->model->toBe('claude-fable-5');

    expect($completion->usage->iterations[1])
        ->type->toBe('fallback_message')
        ->model->toBe('claude-opus-4-8');
});

test('to array from fallback response', function () {
    $completion = CreateResponse::from(messagesCompletionWithFallback(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithFallback());
});

test('from refusal response with recommended model', function () {
    $completion = CreateResponse::from(messagesCompletionWithRefusalAndRecommendedModel(), meta());

    expect($completion)
        ->stop_reason->toBe('refusal')
        ->content->toBeArray()->toHaveCount(0);

    expect($completion->stop_details)
        ->toBeInstanceOf(CreateResponseStopDetails::class)
        ->type->toBe('refusal')
        ->category->toBe('cyber')
        ->explanation->toBe('This request was declined because it could enable cyber harm.')
        ->recommended_model->toBe('claude-opus-4-8');
});

test('to array from refusal response with recommended model', function () {
    $completion = CreateResponse::from(messagesCompletionWithRefusalAndRecommendedModel(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithRefusalAndRecommendedModel());
});

test('from compaction response', function () {
    $completion = CreateResponse::from(messagesCompletionWithCompaction(), meta());

    expect($completion->content[0])
        ->type->toBe('compaction')
        ->content->toBe('Summary of the conversation: The user requested help building a web scraper');

    expect($completion->content[1])
        ->type->toBe('text');
});

test('to array from compaction response', function () {
    $completion = CreateResponse::from(messagesCompletionWithCompaction(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithCompaction());
});

test('from diagnostics response', function () {
    $completion = CreateResponse::from(messagesCompletionWithDiagnostics(), meta());

    expect($completion->diagnostics)
        ->toBeInstanceOf(CreateResponseDiagnostics::class);

    expect($completion->diagnostics->cache_miss_reason)
        ->toBeInstanceOf(CreateResponseDiagnosticsCacheMissReason::class)
        ->type->toBe('system_changed')
        ->cache_missed_input_tokens->toBe(41850);
});

test('from response without diagnostics', function () {
    $completion = CreateResponse::from(messagesCompletion(), meta());

    expect($completion->diagnostics)->toBeNull();
});

test('to array from diagnostics response', function () {
    $completion = CreateResponse::from(messagesCompletionWithDiagnostics(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithDiagnostics());
});

test('from context management response', function () {
    $completion = CreateResponse::from(messagesCompletionWithContextManagement(), meta());

    expect($completion)
        ->context_management->toBe(['applied_edits' => []])
        ->diagnostics->toBeNull();

    expect($completion->usage->iterations)
        ->toBeArray()->toHaveCount(1);

    expect($completion->usage->iterations[0])
        ->type->toBe('message')
        ->model->toBeNull()
        ->inputTokens->toBe(45989)
        ->outputTokens->toBe(43)
        ->cacheCreation->not->toBeNull()
        ->cacheCreation->ephemeral5mInputTokens->toBe(0);
});

test('to array from context management response', function () {
    $completion = CreateResponse::from(messagesCompletionWithContextManagement(), meta());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(messagesCompletionWithContextManagement());
});

test('from response without context management', function () {
    $completion = CreateResponse::from(messagesCompletion(), meta());

    expect($completion->context_management)->toBeNull();
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
