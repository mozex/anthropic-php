<?php

declare(strict_types=1);

use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Completions\CreateResponse as CompletionsCreateResponse;
use Anthropic\Responses\Completions\CreateStreamedResponse as CompletionsCreateStreamedResponse;
use Anthropic\Responses\Completions\StreamResponse as CompletionsStreamResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Responses\Messages\CreateResponse as MessagesCreateResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse as MessagesCreateStreamedResponse;
use Anthropic\Responses\Messages\StreamResponse as MessagesStreamResponse;
use Anthropic\Responses\Models\RetrieveResponse;

class SubclassedMessagesCreateResponse extends MessagesCreateResponse {}

class SubclassedMessagesCreateStreamedResponse extends MessagesCreateStreamedResponse {}

class SubclassedCompletionsCreateResponse extends CompletionsCreateResponse {}

class SubclassedCompletionsCreateStreamedResponse extends CompletionsCreateStreamedResponse {}

class SubclassedBatchResponse extends BatchResponse {}

class SubclassedBatchResultResponse extends BatchResultResponse {}

class SubclassedFileResponse extends FileResponse {}

class SubclassedRetrieveResponse extends RetrieveResponse {}

test('a subclass of a response fakes from its parent fixture', function (string $subclass, string $parent) {
    $fake = $subclass::fake();

    expect($fake)
        ->toBeInstanceOf($parent)
        ->toArray()->toBe($parent::fake()->toArray());
})->with([
    'messages' => [SubclassedMessagesCreateResponse::class, MessagesCreateResponse::class],
    'completions' => [SubclassedCompletionsCreateResponse::class, CompletionsCreateResponse::class],
    'batches' => [SubclassedBatchResponse::class, BatchResponse::class],
    'files' => [SubclassedFileResponse::class, FileResponse::class],
    'models' => [SubclassedRetrieveResponse::class, RetrieveResponse::class],
]);

test('a subclass of a streamed response fakes from its parent fixture', function (string $subclass, string $stream) {
    $fake = $subclass::fake();

    expect($fake)->toBeInstanceOf($stream)
        ->and(iterator_to_array($fake->getIterator()))->not->toBeEmpty();
})->with([
    'messages' => [SubclassedMessagesCreateStreamedResponse::class, MessagesStreamResponse::class],
    'completions' => [SubclassedCompletionsCreateStreamedResponse::class, CompletionsStreamResponse::class],
]);

test('a subclass of batch results fakes from its parent fixture', function () {
    $fake = SubclassedBatchResultResponse::fake();

    expect($fake)->toBeInstanceOf(BatchResultResponse::class)
        ->and(iterator_to_array($fake->getIterator()))->not->toBeEmpty();
});
