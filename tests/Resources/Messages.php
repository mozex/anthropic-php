<?php

use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Responses\Messages\CountTokensResponse;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseContent;
use Anthropic\Responses\Messages\CreateResponseUsage;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\CreateStreamedResponseContentBlockStart;
use Anthropic\Responses\Messages\CreateStreamedResponseDelta;
use Anthropic\Responses\Messages\CreateStreamedResponseMessage;
use Anthropic\Responses\Messages\CreateStreamedResponseUsage;
use Anthropic\Responses\Messages\StreamResponse;
use Anthropic\Responses\Meta\MetaInformation;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;

test('create', function () {
    $client = mockClient('POST', 'messages', [
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ], Anthropic\ValueObjects\Transporter\Response::from(messagesCompletion(), metaHeaders()));

    $result = $client->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->id->toBe('msg_019hiOHAEXQwq1PTeETNEBWe')
        ->type->toBe('message')
        ->role->toBe('assistant')
        ->model->toBe('claude-sonnet-4-6')
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

test('create with adaptive thinking', function () {
    $client = mockClient('POST', 'messages', [
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
        ],
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ], Anthropic\ValueObjects\Transporter\Response::from(messagesCompletionWithThinking(), metaHeaders()));

    $result = $client->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
        ],
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->id->toBe('msg_019hiOHAEXQwq1PTeETNEBWe')
        ->model->toBe('claude-sonnet-4-6')
        ->content->toBeArray()->toHaveCount(3);

    expect($result->content[0])
        ->type->toBe('thinking')
        ->thinking->toBe('Let me analyze this step by step...')
        ->signature->toBe('WaUjzkypQ2mUEVM36O2Txu');

    expect($result->content[2])
        ->type->toBe('text')
        ->text->toBe("Hello! I'm Claude, an AI assistant. How can I help you today?");
});

test('create with adaptive thinking and display omitted', function () {
    $client = mockClient('POST', 'messages', [
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
            'display' => 'omitted',
        ],
        'messages' => ['role' => 'user', 'content' => 'What is 27 * 453?'],
    ], Anthropic\ValueObjects\Transporter\Response::from(messagesCompletionWithOmittedThinking(), metaHeaders()));

    $result = $client->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
            'display' => 'omitted',
        ],
        'messages' => ['role' => 'user', 'content' => 'What is 27 * 453?'],
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->content->toBeArray()->toHaveCount(2);

    expect($result->content[0])
        ->type->toBe('thinking')
        ->thinking->toBe('')
        ->signature->toBe('EosnCkYICxIMMb3LzNrMu');

    expect($result->content[1])
        ->type->toBe('text')
        ->text->toBe('The answer is 12,231.');
});

test('create with adaptive thinking and effort', function () {
    $client = mockClient('POST', 'messages', [
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
        ],
        'output_config' => [
            'effort' => 'medium',
        ],
        'messages' => ['role' => 'user', 'content' => 'What is the capital of France?'],
    ], Anthropic\ValueObjects\Transporter\Response::from(messagesCompletion(), metaHeaders()));

    $result = $client->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
        ],
        'output_config' => [
            'effort' => 'medium',
        ],
        'messages' => ['role' => 'user', 'content' => 'What is the capital of France?'],
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->content->toBeArray()->toHaveCount(1);

    expect($result->content[0])
        ->type->toBe('text');
});

test('create streamed with adaptive thinking', function () {
    $response = new Response(
        headers: metaHeaders(),
        body: new Stream(messagesCompletionStreamWithAdaptiveThinking()),
    );

    $client = mockStreamClient('POST', 'messages', [
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
        ],
        'messages' => ['role' => 'user', 'content' => 'What is the greatest common divisor of 1071 and 462?'],
        'stream' => true,
    ], $response);

    $result = $client->messages()->createStreamed([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'thinking' => [
            'type' => 'adaptive',
        ],
        'messages' => ['role' => 'user', 'content' => 'What is the greatest common divisor of 1071 and 462?'],
    ]);

    expect($result)
        ->toBeInstanceOf(StreamResponse::class);

    $events = iterator_to_array($result->getIterator());

    // message_start, content_block_start (thinking), thinking_delta, signature_delta,
    // content_block_stop, content_block_start (text), text_delta, content_block_stop, message_delta
    expect($events)->toHaveCount(9);

    expect($events[1])
        ->type->toBe('content_block_start')
        ->content_block_start->type->toBe('thinking');

    expect($events[2])
        ->type->toBe('content_block_delta')
        ->delta->type->toBe('thinking_delta')
        ->delta->thinking->toBe("I need to find the GCD of 1071 and 462 using the Euclidean algorithm.\n\n1071 = 2 \xC3\x97 462 + 147");

    expect($events[3])
        ->type->toBe('content_block_delta')
        ->delta->type->toBe('signature_delta')
        ->delta->signature->toBe('EqQBCgIYAhIM1gbcDa9GJwZA2b3hGgxBdjrkzLoky3dl1pkiMOYds');

    expect($events[6])
        ->type->toBe('content_block_delta')
        ->delta->type->toBe('text_delta')
        ->delta->text->toBe('The greatest common divisor of 1071 and 462 is **21**.');
});

test('create throws an exception if stream option is true', function () {
    Anthropic::client('foo')->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
        'stream' => true,
    ]);
})->throws(InvalidArgumentException::class, 'Stream option is not supported. Please use the createStreamed() method instead.');

test('create streamed', function () {
    $response = new Response(
        headers: metaHeaders(),
        body: new Stream(messagesCompletionStream()),
    );

    $client = mockStreamClient('POST', 'messages', [
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
        'stream' => true,
    ], $response);

    $result = $client->messages()->createStreamed([
        'model' => 'claude-sonnet-4-6',
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
        ->content_block_start->toBeInstanceOf(CreateStreamedResponseContentBlockStart::class)
        ->content_block_start->type->toBeNull()
        ->usage->toBeInstanceOf(CreateStreamedResponseUsage::class)
        ->usage->inputTokens->toBe(25);

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});

test('handles error messages in stream', function () {
    $response = new Response(
        body: new Stream(messagesCompletionStreamError())
    );

    $client = mockStreamClient('POST', 'messages', [
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
        'stream' => true,
    ], $response);

    $result = $client->messages()->createStreamed([
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ]);

    expect(fn () => $result->getIterator()->current())
        ->toThrow(function (ErrorException $e) {
            expect($e->getMessage())->toBe('Overloaded')
                ->and($e->getErrorMessage())->toBe('Overloaded')
                ->and($e->getErrorType())->toBe('overloaded_error');
        });
});

test('count tokens', function () {
    $client = mockClient('POST', 'messages/count_tokens', [
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ], Anthropic\ValueObjects\Transporter\Response::from(messagesCountTokens(), metaHeaders()));

    $result = $client->messages()->countTokens([
        'model' => 'claude-sonnet-4-6',
        'messages' => ['role' => 'user', 'content' => 'Hello!'],
    ]);

    expect($result)
        ->toBeInstanceOf(CountTokensResponse::class)
        ->inputTokens->toBe(2095);

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});
