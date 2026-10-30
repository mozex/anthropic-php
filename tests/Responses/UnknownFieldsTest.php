<?php

declare(strict_types=1);

use Anthropic\Responses\Batches\BatchIndividualResponse;
use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\DeletedBatchResponse;
use Anthropic\Responses\Completions\CreateResponse as CompletionsCreateResponse;
use Anthropic\Responses\Completions\CreateStreamedResponse as CompletionsCreateStreamedResponse;
use Anthropic\Responses\Files\DeletedFileResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Responses\Messages\CountTokensResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;

test('responses keep the fields they do not type', function (Closure $build, array $attributes) {
    expect($build($attributes)->toArray())->toBe($attributes);
})->with([
    // composed: future_field is no API field; it stands for one this client doesn't type yet
    'model' => [
        fn (array $attributes) => RetrieveResponse::from($attributes, meta()),
        [...modelRetrieve(), 'future_field' => ['value' => 1]],
    ],
    'model capabilities' => [
        fn (array $attributes) => RetrieveResponse::from($attributes, meta()),
        [...modelRetrieve(), 'capabilities' => [...modelRetrieve()['capabilities'], 'future_capability' => ['supported' => true]]],
    ],
    'model list' => [
        fn (array $attributes) => ListResponse::from($attributes, meta()),
        [...modelList(), 'future_field' => null],
    ],
    'batch' => [
        fn (array $attributes) => BatchResponse::from($attributes, meta()),
        [...batchResponse(), 'future_field' => 'value'],
    ],
    'batch request counts' => [
        fn (array $attributes) => BatchResponse::from($attributes, meta()),
        [...batchResponse(), 'request_counts' => [...batchResponse()['request_counts'], 'future_count' => 0]],
    ],
    'batch list' => [
        fn (array $attributes) => BatchListResponse::from($attributes, meta()),
        [...batchListResponse(), 'future_field' => 'value'],
    ],
    'deleted batch' => [
        fn (array $attributes) => DeletedBatchResponse::from($attributes, meta()),
        [...deletedBatchResponse(), 'future_field' => 'value'],
    ],
    'batch result line' => [
        fn (array $attributes) => BatchIndividualResponse::from($attributes),
        [...batchIndividualCanceledResponse(), 'future_field' => 'value'],
    ],
    'file' => [
        fn (array $attributes) => FileResponse::from($attributes, meta()),
        [...fileResponse(), 'future_field' => 'value'],
    ],
    'file list' => [
        fn (array $attributes) => FileListResponse::from($attributes, meta()),
        [...fileListResponse(), 'future_field' => 'value'],
    ],
    'deleted file' => [
        fn (array $attributes) => DeletedFileResponse::from($attributes, meta()),
        [...deletedFileResponse(), 'future_field' => 'value'],
    ],
    'completion' => [
        fn (array $attributes) => CompletionsCreateResponse::from($attributes),
        [...completion(), 'future_field' => 'value'],
    ],
    'streamed completion' => [
        fn (array $attributes) => CompletionsCreateStreamedResponse::from($attributes),
        [...completion(), 'future_field' => 'value'],
    ],
    // https://platform.claude.com/docs/en/build-with-claude/context-editing
    'count tokens with context management' => [
        fn (array $attributes) => CountTokensResponse::from($attributes, meta()),
        ['input_tokens' => 25000, 'context_management' => ['original_input_tokens' => 70000]],
    ],
]);

test('stream events keep the fields they do not type', function () {
    // https://platform.claude.com/docs/en/build-with-claude/thinking
    $start = CreateStreamedResponse::from(['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'thinking', 'thinking' => '', 'signature' => '']]);

    // composed: the docs elide applied_edits on the streamed message_delta; these are its non-streaming values
    $delta = CreateStreamedResponse::from([
        'type' => 'message_delta',
        'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null],
        'usage' => ['output_tokens' => 1024],
        'context_management' => ['applied_edits' => [['type' => 'clear_thinking_20251015', 'cleared_thinking_turns' => 3, 'cleared_input_tokens' => 15000]]],
    ]);

    expect($start->toArray())
        ->not->toHaveKey('content_block')
        ->and($start->toArray()['content_block_start'])->toHaveKey('signature', '')
        ->and($delta->toArray()['context_management'])->toBe(['applied_edits' => [['type' => 'clear_thinking_20251015', 'cleared_thinking_turns' => 3, 'cleared_input_tokens' => 15000]]]);
});

test('message_start keeps its usage once', function () {
    $event = CreateStreamedResponse::from(messagesCompletionStreamFirstChunk())->toArray();

    expect($event['usage'])->toMatchArray(['input_tokens' => 10, 'output_tokens' => 1])
        ->and($event['message'])->not->toHaveKey('usage');
});

test('an errored batch result keeps its inner error once, with the request id', function () {
    $result = BatchIndividualResponse::from(batchIndividualErroredResponse())->toArray();

    expect($result['result']['error'])->toBe([
        ...batchIndividualErroredResponse()['result']['error']['error'],
        'request_id' => 'req_abcdef123456',
    ]);
});
