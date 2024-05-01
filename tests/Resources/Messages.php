<?php

use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseContent;
use Anthropic\Responses\Messages\CreateResponseUsage;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\CreateStreamedResponseDelta;
use Anthropic\Responses\Messages\CreateStreamedResponseMessage;
use Anthropic\Responses\Messages\CreateStreamedResponseUsage;
use Anthropic\Responses\Messages\StreamResponse;
use Anthropic\Responses\Meta\MetaInformation;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;

test('create', function () {
    $client = mockClient('POST', 'messages', [
        'model' => 'claude-3-haiku-20240307',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ], \Anthropic\ValueObjects\Transporter\Response::from(chatCompletion(), metaHeaders()));

    $result = $client->messages()->create([
        'model' => 'claude-3-haiku-20240307',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->id->toBe('msg_019hiOHAEXQwq1PTeETNEBWe')
        ->type->toBe('message')
        ->role->toBe('assistant')
        ->model->toBe('claude-3-opus-20240229')
        ->stop_sequence->toBeNull()
        ->stop_reason->toBe('end_turn')
        ->content->toBeArray()->toHaveCount(1)
        ->content->each->toBeInstanceOf(CreateResponseContent::class)
        ->usage->toBeInstanceOf(CreateResponseUsage::class);

    expect($result->content[0])
        ->type->toBe('text')
        ->text->toBe("Hello! I'm Claude, an AI assistant. How can I help you today?");

    expect($result->usage)
        ->inputTokens->toBe(10)
        ->outputTokens->toBe(20);

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('create throws an exception if stream option is true', function () {
    Anthropic::client('foo')->messages()->create([
        'model' => 'claude-3-opus-20240229',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
        'stream' => true,
    ]);
})->throws(Anthropic\Exceptions\InvalidArgumentException::class, 'Stream option is not supported. Please use the createStreamed() method instead.');

test('create streamed', function () {
    $response = new Response(
        headers: metaHeaders(),
        body: new Stream(chatCompletionStream()),
    );

    $client = mockStreamClient('POST', 'messages', [
        'model' => 'claude-3-opus-20240229',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
        'stream' => true,
    ], $response);

    $result = $client->messages()->createStreamed([
        'model' => 'claude-3-opus-20240229',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ]);

    expect($result)
        ->toBeInstanceOf(StreamResponse::class)
        ->toBeInstanceOf(IteratorAggregate::class);

    expect($result->getIterator())
        ->toBeInstanceOf(Iterator::class);

    expect($result->getIterator()->current())
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('message_start')
        ->index->toBeNull()
        ->delta->toBeInstanceOf(CreateStreamedResponseDelta::class)
        ->delta->text->toBeNull()
        ->message->toBeInstanceOf(CreateStreamedResponseMessage::class)
        ->message->id->toBe('msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY')
        ->usage->toBeInstanceOf(CreateStreamedResponseUsage::class)
        ->usage->inputTokens->toBe(25);

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('handles error messages in stream', function () {
    $response = new Response(
        body: new Stream(chatCompletionStreamError())
    );

    $client = mockStreamClient('POST', 'messages', [
        'model' => 'claude-3-opus-20240229',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
        'stream' => true,
    ], $response);

    $result = $client->messages()->createStreamed([
        'model' => 'claude-3-opus-20240229',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ]);

    expect(fn () => $result->getIterator()->current())
        ->toThrow(function (Anthropic\Exceptions\ErrorException $e) {
            expect($e->getMessage())->toBe('Overloaded')
                ->and($e->getErrorMessage())->toBe('Overloaded')
                ->and($e->getErrorType())->toBe('overloaded_error');
        });
});
