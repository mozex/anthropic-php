<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\StreamResponse;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * A client streaming this body once, every completion it reports collected.
 *
 * @param  resource  $body
 * @param  list<StreamResponse<mixed>>  $completed
 */
function completingClient($body, array &$completed): Client
{
    return Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'text/event-stream', 'request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'], $body),
        ]))]))
        ->withStreamCompletionHandler(function (StreamResponse $stream) use (&$completed): void {
            $completed[] = $stream;
        })
        ->make();
}

test('a stream read to its end reports once, with the usage folded from message_start and message_delta', function () {
    $completed = [];
    $stream = completingClient(messagesCompletionStream(), $completed)->messages()->createStreamed(['model' => 'claude-sonnet-4-6']);

    iterator_to_array($stream);

    expect($completed)->toHaveCount(1)
        ->and($completed[0])->toBe($stream)
        ->and($stream->completed())->toBeTrue()
        ->and($stream->usage())->toBe(['input_tokens' => 25, 'output_tokens' => 15])
        ->and($stream->model())->toBe('claude-sonnet-4-6')
        ->and($stream->meta()->requestId)->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        ->and($stream->durationMs())->toBeGreaterThanOrEqual(0);

    // only a snapshot kept, so the stream really goes: it doesn't report again
    $reports = [];
    $snapshot = Anthropic::factory()->withApiKey('foo')
        ->withHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'text/event-stream'], messagesCompletionStream())]))]))
        ->withStreamCompletionHandler(function (StreamResponse $stream) use (&$reports): void {
            $reports[] = $stream->completed();
        })
        ->make()->messages()->createStreamed(['model' => 'claude-sonnet-4-6']);

    iterator_to_array($snapshot);
    unset($snapshot);

    expect($reports)->toBe([true]);
});

test('the usage folds message_delta\'s counts over message_start\'s, skipping nulls', function () {
    // the web search stream of the streaming page; composed: a null count on its message_delta
    $sse = messagesStreamDocsExamples()['web search'];
    $sse = str_replace('"usage":{"input_tokens":10682,"cache_creation_input_tokens":0,"cache_read_input_tokens":0,"output_tokens":510', '"usage":{"input_tokens":10682,"cache_creation_input_tokens":null,"cache_read_input_tokens":0,"output_tokens":510', $sse, $replaced);
    $stream = new StreamResponse(CreateStreamedResponse::class, new Response(200, [], $sse));

    iterator_to_array($stream);

    expect($replaced)->toBe(1)
        ->and($stream->usage()['input_tokens'])->toBe(10682)
        ->and($stream->usage()['output_tokens'])->toBe(510)
        ->and($stream->usage()['cache_creation_input_tokens'])->toBe(0)
        ->and($stream->usage()['server_tool_use'])->toBe(['web_search_requests' => 1]);
});

test('after a server-side fallback, the model is the one that answered', function () {
    // composed: the streaming page's message_start under the declined model; the docs' fallback events after it
    $start = 'event: message_start'."\n".'data: {"type":"message_start","message":{"id":"msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY","type":"message","role":"assistant","content":[],"model":"claude-fable-5","stop_reason":null,"stop_sequence":null,"usage":{"input_tokens":25,"output_tokens":1}}}'."\n\n";
    $stream = new StreamResponse(CreateStreamedResponse::class, new Response(200, [], $start.messagesStreamDocsExamples()['server-side fallback']));

    iterator_to_array($stream);

    expect($stream->model())->toBe('claude-opus-4-8')
        ->and($stream->usage()['output_tokens'])->toBe(264);
});

test('a handler that throws doesn\'t replace the API\'s error, nor fail the code that drops the stream', function () {
    $throwing = fn ($body): StreamResponse => new StreamResponse(CreateStreamedResponse::class, new Response(200, [], $body), fn () => throw new LogicException('the ledger is down'));

    expect(fn () => iterator_to_array($throwing(messagesCompletionStreamError())))->toThrow(OverloadedException::class);

    $dropped = $throwing(messagesCompletionStream());

    foreach ($dropped as $event) {
        break;
    }

    unset($dropped, $event);

    expect(true)->toBeTrue();
});

test('a copy of a stream reports nothing of its own', function () {
    $reports = 0;
    $stream = new StreamResponse(CreateStreamedResponse::class, new Response(200, [], ''), function () use (&$reports): void {
        $reports++;
    });

    $copy = clone $stream;
    unset($copy, $stream);

    expect($reports)->toBe(1);
});

test('a stream dropped before its end reports what it has when it goes', function () {
    $completed = [];
    $stream = completingClient(messagesCompletionStream(), $completed)->messages()->createStreamed(['model' => 'claude-sonnet-4-6']);

    foreach ($stream as $event) {
        break;
    }

    expect($completed)->toBe([]);

    unset($stream, $event);

    expect($completed)->toHaveCount(1)
        ->and($completed[0]->completed())->toBeFalse()
        ->and($completed[0]->usage())->toBe(['input_tokens' => 25, 'output_tokens' => 1]);
});

test('a stream that fails reports before the error is thrown', function () {
    $completed = [];
    $stream = completingClient(messagesCompletionStreamError(), $completed)->messages()->createStreamed(['model' => 'claude-sonnet-4-6']);

    expect(fn () => iterator_to_array($stream))->toThrow(OverloadedException::class)
        ->and($completed)->toHaveCount(1)
        ->and($completed[0]->completed())->toBeFalse();
});

test('a stream never read reports when it goes, with no usage', function () {
    $completed = [];
    $stream = completingClient(messagesCompletionStream(), $completed)->messages()->createStreamed(['model' => 'claude-sonnet-4-6']);

    unset($stream);

    expect($completed)->toHaveCount(1)
        ->and($completed[0]->usage())->toBe([])
        ->and($completed[0]->model())->toBeNull();
});

test('a client with options keeps the handler', function () {
    $completed = [];
    $stream = completingClient(messagesCompletionStream(), $completed)->withOptions(['max_retries' => 0])->messages()->createStreamed(['model' => 'claude-sonnet-4-6']);

    iterator_to_array($stream);

    expect($completed)->toHaveCount(1);
});
