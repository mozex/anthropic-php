<?php

declare(strict_types=1);

use Anthropic\Responses\Batches\BatchIndividualResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Completions\CreateStreamedResponse as CompletionsCreateStreamedResponse;
use Anthropic\Responses\Completions\StreamResponse as CompletionsStreamResponse;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\StreamResponse;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/**
 * Counts reads and can cap how many bytes each read returns, like a network stream would.
 */
class ShortReadStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public int $reads = 0;

    public function __construct(private StreamInterface $stream, private int $maxLength = PHP_INT_MAX) {}

    public function read($length): string
    {
        $this->reads++;

        return $this->stream->read(min($length, $this->maxLength));
    }
}

function messagesStream(StreamInterface|string $body): StreamResponse
{
    return new StreamResponse(CreateStreamedResponse::class, new Response(200, [], $body));
}

/**
 * @return array<int, array<string, mixed>>
 */
function messagesEvents(StreamInterface|string $body): array
{
    return array_map(
        fn (CreateStreamedResponse $response): array => $response->toArray(),
        iterator_to_array(messagesStream($body), false),
    );
}

test('messages streams are read in chunks', function () {
    $stream = new ShortReadStream(Utils::streamFor(messagesCompletionStream()));

    expect(messagesEvents($stream))->toHaveCount(6)
        ->and($stream->reads)->toBeLessThanOrEqual(3);
});

test('completions streams are read in chunks', function () {
    $stream = new ShortReadStream(Utils::streamFor(completionStream()));

    $responses = iterator_to_array(new CompletionsStreamResponse(CompletionsCreateStreamedResponse::class, new Response(200, [], $stream)), false);

    expect($responses)->toHaveCount(18)
        ->and($stream->reads)->toBeLessThanOrEqual(3);
});

test('batch results are read in chunks', function () {
    $stream = new ShortReadStream(Utils::streamFor(batchResultsStream()));

    $results = iterator_to_array(new BatchResultResponse(new Response(200, [], $stream)), false);

    expect($results)->toHaveCount(4)
        ->each->toBeInstanceOf(BatchIndividualResponse::class)
        ->and($stream->reads)->toBeLessThanOrEqual(3);
});

test('events split across short reads arrive intact', function () {
    $expected = messagesEvents(Utils::streamFor(messagesCompletionStreamWithAdaptiveThinking()));

    expect(messagesEvents(new ShortReadStream(Utils::streamFor(messagesCompletionStreamWithAdaptiveThinking()), 7)))
        ->toBe($expected)
        ->and($expected)->not->toBe([]);
});

test('batch results split across short reads arrive intact', function () {
    $results = fn (StreamInterface $body): array => array_map(
        fn (BatchIndividualResponse $result): array => $result->toArray(),
        iterator_to_array(new BatchResultResponse(new Response(200, [], $body)), false),
    );

    expect($results(new ShortReadStream(Utils::streamFor(batchResultsStream()), 5)))
        ->toBe($results(Utils::streamFor(batchResultsStream())));
});

test('the data lines of one event are joined', function () {
    // composed: no docs example
    $body = "event: message_delta\r\n"
        ."data: {\"type\":\"message_delta\",\r\n"
        ."data: \"delta\":{\"stop_reason\":\"end_turn\",\"stop_sequence\":null},\"usage\":{\"output_tokens\":15}}\r\n"
        ."\r\n";

    expect(messagesEvents($body))->sequence(
        fn ($event) => $event->type->toBe('message_delta')
            ->delta->stop_reason->toBe('end_turn')
            ->usage->output_tokens->toBe(15),
    );
});

test('comments and other fields are ignored', function () {
    // composed: no docs example
    $body = ": keep-alive\n"
        ."id: 1\n"
        ."retry: 1000\n"
        ."event: content_block_stop\n"
        ."data: {\"type\":\"content_block_stop\",\"index\":0}\n"
        ."\n";

    expect(messagesEvents($body))->sequence(
        fn ($event) => $event->type->toBe('content_block_stop')->index->toBe(0),
    );
});

test('unknown events are yielded and pings are skipped', function () {
    // composed: no docs example
    $body = "event: ping\ndata: {\"type\": \"ping\"}\n\n"
        ."event: future_event\ndata: {\"type\":\"future_event\",\"index\":2}\n\n"
        ."event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n";

    expect(messagesEvents($body))->sequence(
        fn ($event) => $event->type->toBe('future_event')->index->toBe(2),
    );
});

test('a stream that ends without a final line break still yields its last event', function () {
    // composed: no docs example
    expect(messagesEvents("event: content_block_stop\ndata: {\"type\":\"content_block_stop\",\"index\":1}"))->sequence(
        fn ($event) => $event->type->toBe('content_block_stop')->index->toBe(1),
    );
});

test('a trailing nul byte is trimmed before decoding', function () {
    // composed: no docs example
    expect(messagesEvents("event: content_block_stop\ndata: {\"type\":\"content_block_stop\",\"index\":0}\0\n\n"))->sequence(
        fn ($event) => $event->type->toBe('content_block_stop'),
    );
});

test('an event with empty data is not dispatched', function () {
    // composed: no docs example
    expect(messagesEvents("event: ping\ndata:\n\nevent: content_block_stop\ndata: {\"type\":\"content_block_stop\",\"index\":0}\n\n"))
        ->toHaveCount(1);
});

test('iterating a messages stream again continues where the last iteration stopped', function () {
    $stream = messagesStream(Utils::streamFor(messagesCompletionStream()));

    $first = $stream->getIterator()->current();
    $rest = iterator_to_array($stream, false);

    expect($first->type)->toBe('message_start')
        ->and($rest)->toHaveCount(5)
        ->and($rest[0]->type)->toBe('content_block_start');
});

test('iterating batch results again continues where the last iteration stopped', function () {
    $results = new BatchResultResponse(new Response(200, [], Utils::streamFor(batchResultsStream())));

    $first = $results->getIterator()->current();

    expect($first)->toBeInstanceOf(BatchIndividualResponse::class)
        ->and(iterator_to_array($results, false))->toHaveCount(3);
});
