<?php

declare(strict_types=1);

use Anthropic\Exceptions\ErrorException;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\CreateResponseContent;
use Anthropic\Responses\Messages\CreateStreamedResponse;
use Anthropic\Responses\Messages\MessageAccumulator;
use Anthropic\Responses\Messages\StreamResponse;
use GuzzleHttp\Psr7\Response;

/**
 * A stream of a docs example, or of SSE text.
 *
 * @return StreamResponse<CreateStreamedResponse>
 */
function docsStream(string $nameOrText, ?Closure $onComplete = null): StreamResponse
{
    $text = messagesStreamDocsExamples()[$nameOrText] ?? $nameOrText;

    return new StreamResponse(CreateStreamedResponse::class, new Response(200, [], $text), $onComplete);
}

/**
 * A docs stream fragment behind a message_start of its own.
 */
function docsStreamWithStart(string $name): string
{
    // composed: the streaming page's message_start, for the fragments that show only their own events
    return "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"id\":\"msg_014p7gG3wDgGV9EUtLvnow3U\",\"type\":\"message\",\"role\":\"assistant\",\"model\":\"claude-opus-5\",\"stop_sequence\":null,\"usage\":{\"input_tokens\":472,\"output_tokens\":2},\"content\":[],\"stop_reason\":null}}\n\n"
        .messagesStreamDocsExamples()[$name];
}

test('the final message of a streamed docs response is the response the API sends whole', function (string $name) {
    $response = CreateResponse::from(json_decode(messagesDocsExamples()[$name], true), meta());

    expect(docsExampleJson(CreateStreamedResponse::fake($response)->finalMessage()->toArray()))->toBe(docsExampleJson($response->toArray()));
})->with(fn (): array => array_keys(messagesDocsExamples()));

test('the final message of a streamed fixture response is the response the API sends whole', function (string $fixture) {
    $response = CreateResponse::from($fixture(), meta());

    expect(docsExampleJson(CreateStreamedResponse::fake($response)->finalMessage()->toArray()))->toBe(docsExampleJson($response->toArray()));
})->with([
    'messagesCompletion', 'messagesCompletionWithThinking', 'messagesCompletionWithOmittedThinking', 'messagesCompletionWithCache',
    'messagesCompletionWithExtendedUsage', 'messagesCompletionWithToolCalls', 'messagesCompletionWithToolCallsAndCaller',
    'messagesCompletionWithContainerUpload', 'messagesCompletionWithWebSearch', 'messagesCompletionWithCodeExecution',
    'messagesCompletionWithRefusal', 'messagesCompletionWithDocumentCitations', 'messagesCompletionWithFastMode',
    'messagesCompletionWithThinkingTokens', 'messagesCompletionWithFallback', 'messagesCompletionWithRefusalAndRecommendedModel',
    'messagesCompletionWithCompaction', 'messagesCompletionWithDiagnostics', 'messagesCompletionWithContextManagement',
]);

test('a cited answer streams its citations back into the text block', function () {
    $response = CreateResponse::from(messagesCompletionWithDocumentCitations(), meta());
    $cited = fn (CreateResponse $message): array => array_values(array_filter($message->content, fn (CreateResponseContent $block): bool => $block->citations !== null));

    $streamed = $cited(CreateStreamedResponse::fake($response)->finalMessage());

    expect($streamed)->not->toBeEmpty()
        ->and($streamed[0]->citations)->toBe($cited($response)[0]->citations);
});

test('the final message of the default fake is the default response fake', function () {
    expect(docsExampleJson(CreateStreamedResponse::fake(CreateResponse::fake())->finalMessage()->toArray()))
        ->toBe(docsExampleJson(CreateResponse::fake()->toArray()));
});

test('the docs tool use stream adds up to its text, its decoded input, its stop and its usage', function () {
    $message = docsStream('tool use')->finalMessage();

    expect($message->content[0]->text)->toBe("Okay, let's check the weather for San Francisco, CA:")
        ->and($message->content[1]->type)->toBe('tool_use')
        ->and($message->content[1]->input)->toBe(['location' => 'San Francisco, CA'])
        ->and($message->stop_reason)->toBe('tool_use')
        ->and($message->usage->inputTokens)->toBe(472)
        ->and($message->usage->outputTokens)->toBe(89)
        ->and($message->id)->toBe('msg_014p7gG3wDgGV9EUtLvnow3U');
});

test('the docs thinking stream adds up to its thinking, its signature and its text', function () {
    $message = docsStream('thinking')->finalMessage();

    expect($message->content[0]->thinking)->toBe("I need to find the GCD of 1071 and 462 using the Euclidean algorithm.\n\n1071 = 2 × 462 + 147\n462 = 3 × 147 + 21\n147 = 7 × 21 + 0\nThe remainder is 0, so GCD(1071, 462) = 21.")
        ->and($message->content[0]->signature)->toBe('EqQBCgIYAhIM1gbcDa9GJwZA2b3hGgxBdjrkzLoky3dl1pkiMOYds')
        ->and($message->content[1]->text)->toBe('The greatest common divisor of 1071 and 462 is **21**.')
        ->and($message->stop_reason)->toBe('end_turn')
        // the page's message_start leaves usage out
        ->and($message->usage->outputTokens)->toBe(0);
});

test('the docs web search stream keeps the server tool call, its result and the final usage', function () {
    $message = docsStream('web search')->finalMessage();

    expect(array_map(fn (CreateResponseContent $block): string => $block->type, $message->content))->toBe(['text', 'server_tool_use', 'web_search_tool_result', 'text'])
        ->and($message->content[1]->input)->toBe(['query' => 'weather NYC today'])
        ->and($message->content[3]->text)->toBe("Here's the current weather information for New York City:\n\n# Weather in New York City\n\n")
        ->and($message->usage->inputTokens)->toBe(10682)
        ->and($message->usage->outputTokens)->toBe(510)
        ->and($message->usage->serverToolUse?->webSearchRequests)->toBe(1);
});

test('the docs refusal stream carries its stop details, and on-demand compaction its whole block', function () {
    $refusal = docsStream('refusal')->finalMessage();
    $compaction = docsStream('on-demand compaction')->finalMessage();

    expect($refusal->stop_reason)->toBe('refusal')
        ->and($refusal->stop_details?->category)->toBe('cyber')
        ->and($refusal->content[0]->text)->toBe('Hello..')
        ->and($compaction->content[0]->type)->toBe('compaction')
        ->and($compaction->stop_reason)->toBe('compaction')
        ->and($compaction->usage->iterations[0]->type ?? null)->toBe('compaction');
});

test('the docs stream fragments build a message once a message_start is in front', function (string $name, int $blocks) {
    // the advisor, toolset and programmatic fragments start past index 0, so their blocks are skipped
    expect(docsStream(docsStreamWithStart($name))->finalMessage()->content)->toHaveCount($blocks);
})->with([
    'mcp connector' => ['mcp connector', 4],
    'advisor' => ['advisor', 0],
    'toolset' => ['toolset', 0],
    'programmatic tool calling' => ['programmatic tool calling', 0],
    'server-side fallback' => ['server-side fallback', 1],
    'context editing' => ['context editing', 0],
]);

test('the mcp connector fragment decodes its tool call input', function () {
    $message = docsStream(docsStreamWithStart('mcp connector'))->finalMessage();

    expect($message->content[1]->input)->toBeArray()->not->toBeEmpty();
});

test('after a server-side fallback the final message names the model that answered, as the API does whole', function () {
    $message = docsStream(docsStreamWithStart('server-side fallback'))->finalMessage();

    expect($message->model)->toBe('claude-opus-4-8')
        ->and($message->content[0]->type)->toBe('fallback');
});

test('context management and input transformations reach the final message from message_delta', function () {
    $edited = docsStream(docsStreamWithStart('context editing'))->finalMessage();
    $transformed = docsStream('input transformations')->finalMessage();

    expect($edited->context_management)->not->toBeNull()
        ->and($transformed->input_transformations)->not->toBeNull();
});

test('text() yields only the text deltas, from where the stream stands', function () {
    expect(implode('', iterator_to_array(docsStream('tool use')->text(), false)))->toBe("Okay, let's check the weather for San Francisco, CA:")
        ->and(iterator_to_array(docsStream('thinking')->text(), false))->toBe(['The greatest common divisor of 1071 and 462 is **21**.']);
});

test('finalText() joins the text blocks as they are, and is empty without one', function () {
    expect(docsStream('web search')->finalText())->toBe("I'll check the current weather in New York City for you.Here's the current weather information for New York City:\n\n# Weather in New York City\n\n")
        ->and(docsStream('on-demand compaction')->finalText())->toBe('');
});

test('currentMessage() is the message so far, and finalMessage() reads on from there', function () {
    $stream = docsStream('tool use');

    expect($stream->currentMessage())->toBeNull();

    foreach ($stream as $event) {
        if ($event->delta->type === 'text_delta') {
            break;
        }
    }

    $current = $stream->currentMessage();

    expect($current?->content[0]->text)->toBe('Okay')
        ->and($current?->stop_reason)->toBeNull()
        ->and($stream->finalMessage()->content[1]->input)->toBe(['location' => 'San Francisco, CA'])
        ->and($stream->completed())->toBeTrue();
});

test('the callbacks get each delta, each finished block and the message, before the loop gets the event', function () {
    $seen = [];
    $stream = docsStream('thinking')
        ->onEvent(function (CreateStreamedResponse $event) use (&$seen) {
            $seen[] = 'event:'.$event->type;
        })
        ->onThinking(function (string $delta) use (&$seen) {
            $seen[] = 'thinking';
        })
        ->onText(function (string $delta) use (&$seen) {
            $seen[] = 'text:'.$delta;
        })
        ->onContentBlock(function (CreateResponseContent $block) use (&$seen) {
            $seen[] = 'block:'.$block->type;
        })
        ->onMessage(function (CreateResponse $message) use (&$seen) {
            $seen[] = 'message:'.$message->stop_reason;
        });

    foreach ($stream as $event) {
        $seen[] = 'loop:'.$event->type;
    }

    expect(array_count_values($seen)['thinking'])->toBe(4)
        ->and(array_slice($seen, -11))->toBe([
            'event:content_block_start', 'loop:content_block_start',
            'event:content_block_delta', 'text:The greatest common divisor of 1071 and 462 is **21**.', 'loop:content_block_delta',
            'event:content_block_stop', 'block:text', 'loop:content_block_stop',
            'event:message_delta', 'loop:message_delta',
            // at message_stop, which the loop never gets
            'message:end_turn',
        ]);
});

test('a tool call reaches onContentBlock with its input decoded', function () {
    $blocks = [];

    docsStream('tool use')->onContentBlock(function (CreateResponseContent $block) use (&$blocks) {
        $blocks[] = $block;
    })->finalMessage();

    expect($blocks[1]->input)->toBe(['location' => 'San Francisco, CA']);
});

test('an event the fold cannot place is skipped, and the stream reads to its end', function () {
    // composed: the tool use stream's own events, out of place: a delta for a block that never started, a block that
    // skips an index, and a second message_start
    $text = messagesStreamDocsExamples()['refusal'];
    $text = str_replace("event: message_delta\n", implode('', [
        "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"index\":7,\"delta\":{\"type\":\"text_delta\",\"text\":\"Okay\"}}\n\n",
        "event: content_block_start\ndata: {\"type\":\"content_block_start\",\"index\":5,\"content_block\":{\"type\":\"text\",\"text\":\"\"}}\n\n",
        "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"id\":\"msg_014p7gG3wDgGV9EUtLvnow3U\",\"type\":\"message\",\"role\":\"assistant\",\"model\":\"claude-opus-5\",\"stop_sequence\":null,\"usage\":{\"input_tokens\":472,\"output_tokens\":2},\"content\":[],\"stop_reason\":null}}\n\n",
        "event: message_delta\n",
    ]), $text);

    expect(iterator_to_array(docsStream($text), false))->toHaveCount(8);

    $message = docsStream($text)->finalMessage();

    expect($message->id)->toBe('msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY')
        ->and($message->content)->toHaveCount(1)
        ->and($message->content[0]->text)->toBe('Hello..');
});

test('a stream with no message_start holds no message', function () {
    expect(fn () => docsStream('advisor')->finalMessage())->toThrow(LogicException::class, 'without a message_start');
});

test('a mid-stream error still throws from finalMessage()', function () {
    $stream = new StreamResponse(CreateStreamedResponse::class, new Response(200, [], fopen(__DIR__.'/../../Helpers/Streams/MessagesCompletionCreateError.txt', 'r')));

    expect(fn () => $stream->finalMessage())->toThrow(ErrorException::class);
});

test('the accumulator folds decoded events read any other way', function () {
    $accumulator = new MessageAccumulator;
    preg_match_all('/^data: (.+)$/m', messagesStreamDocsExamples()['tool use'], $matches);

    foreach ($matches[1] as $data) {
        $accumulator->add(json_decode($data, true));
    }

    expect($accumulator->completed())->toBeTrue()
        ->and($accumulator->message()?->content[1]->input)->toBe(['location' => 'San Francisco, CA'])
        ->and($accumulator->toArray()['content'][1]['input'] ?? null)->toBe(['location' => 'San Francisco, CA'])
        ->and((new MessageAccumulator)->message())->toBeNull();
});

/**
 * An accumulator past the docs tool use stream's message_start.
 */
function startedAccumulator(): MessageAccumulator
{
    return (new MessageAccumulator)->add(['type' => 'message_start', 'message' => ['id' => 'msg_014p7gG3wDgGV9EUtLvnow3U', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5', 'stop_sequence' => null, 'usage' => ['input_tokens' => 472, 'output_tokens' => 2], 'content' => [], 'stop_reason' => null]]);
}

test('a threshold compaction adds only the keys its delta sends', function () {
    // composed: the threshold compaction page's summary, as the fake streams it, with no encrypted content
    $accumulator = startedAccumulator()
        ->add(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'compaction', 'content' => null]])
        ->add(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'compaction_delta', 'content' => 'Summary of the conversation: The user requested help building a web scraper']]);

    expect($accumulator->toArray()['content'][0] ?? null)->toBe(['type' => 'compaction', 'content' => 'Summary of the conversation: The user requested help building a web scraper']);
});

test('a tool call with no input comes out of toArray() as {}, as the API wants it back', function () {
    // composed: the docs tool use stream's tool call, with only its empty first delta
    $accumulator = startedAccumulator()
        ->add(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01T1x1fJ34qAmk2tNTrN7Up6', 'name' => 'get_weather', 'input' => []]])
        ->add(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '']])
        ->add(['type' => 'content_block_stop', 'index' => 0]);

    expect(json_encode($accumulator->toArray()['content'][0] ?? null))->toBe('{"type":"tool_use","id":"toolu_01T1x1fJ34qAmk2tNTrN7Up6","name":"get_weather","input":{}}')
        ->and($accumulator->message()?->content[0]->input)->toBe([]);
});

test('a message_stop completes the stream even without a message_start', function () {
    expect((new MessageAccumulator)->add(['type' => 'message_stop'])->completed())->toBeTrue();
});

test('a callback cannot read the stream it is called from', function () {
    $stream = docsStream('tool use');
    $stream->onText(fn () => iterator_to_array($stream->text()));

    expect(fn () => $stream->finalMessage())->toThrow(LogicException::class, 'currentMessage()');
});

test('as() hands the stream to a class that extends it, which reads it from the start and alone reports it', function () {
    $reports = 0;
    $stream = docsStream('refusal', function () use (&$reports) {
        $reports++;
    });

    $copy = $stream->as(ExtendedStreamResponse::class);
    unset($stream);

    expect($copy)->toBeInstanceOf(ExtendedStreamResponse::class)
        ->and($copy->finalText())->toBe('Hello..')
        ->and($reports)->toBe(1);
});

test('as() carries the callbacks over, and refuses a stream already read', function () {
    $texts = [];
    $stream = docsStream('refusal')->onText(function (string $delta) use (&$texts) {
        $texts[] = $delta;
    });

    $stream->as(ExtendedStreamResponse::class)->finalMessage();

    $read = docsStream('refusal');
    $read->finalMessage();

    expect($texts)->toBe(['Hello..'])
        ->and(fn () => $read->as(ExtendedStreamResponse::class))->toThrow(LogicException::class, 'before reading');
});

/**
 * @extends StreamResponse<CreateStreamedResponse>
 */
class ExtendedStreamResponse extends StreamResponse {}
