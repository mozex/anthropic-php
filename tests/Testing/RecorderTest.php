<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Exceptions\NotFoundException;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Testing\Blocks;
use Anthropic\Testing\Recorder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * A client whose HTTP answers come from the queue (an empty one fails any request that reaches it), with the recorder
 * as its middleware.
 *
 * @param  list<Response>  $answers
 */
function recordedClient(Recorder $recorder, array $answers = []): Client
{
    $guzzle = new GuzzleClient(['handler' => HandlerStack::create(new MockHandler($answers))]);

    return Anthropic::factory()
        ->withApiKey('sk-ant-recorder-key')
        ->withHttpClient($guzzle)
        ->withStreamHandler(fn ($request) => $guzzle->send($request, ['stream' => true]))
        ->withMiddleware($recorder)
        ->make();
}

beforeEach(function () {
    $this->cassette = sys_get_temp_dir().'/anthropic-recorder-'.bin2hex(random_bytes(6)).'/summary.json';
});

afterEach(function () {
    if (is_file($this->cassette)) {
        unlink($this->cassette);
        rmdir(dirname($this->cassette));
    }
});

test('a test records its calls once, then replays them with no HTTP call', function () {
    $answer = CreateResponse::fake(['content' => [Blocks::toolUse('get_time')], 'stop_reason' => 'tool_use'])->toArray();
    $recorder = Recorder::cassette($this->cassette);

    expect($recorder->recording())->toBeTrue();

    // composed: an answer's headers, the organization's among them
    $recorded = recordedClient($recorder, [new Response(200, ['Content-Type' => 'application/json', 'request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG', 'anthropic-organization-id' => 'org-secret', 'anthropic-workspace-id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy', 'set-cookie' => 'session=1'], (string) json_encode($answer))])
        ->messages()->create(['model' => 'claude-opus-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'What time is it?']]]);

    $file = (string) file_get_contents($this->cassette);
    $replayer = Recorder::cassette($this->cassette);
    $replayed = recordedClient($replayer)->messages()->create(['model' => 'claude-opus-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'What time is it?']]]);

    expect($replayer->recording())->toBeFalse()
        ->and(json_encode($replayed->toArray()))->toBe(json_encode($recorded->toArray()))
        ->and($replayed->meta()->requestId)->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        // the answer's `{}` replays as `{}`
        ->and($file)->toContain('"input": {}')
        ->and($file)->toContain('"path": "/v1/messages"')
        ->and($file)->not->toContain('sk-ant-recorder-key')
        ->and($file)->not->toContain('What time is it?')
        ->and($file)->not->toContain('org-secret')
        ->and($file)->not->toContain('wrkspc_')
        ->and($file)->not->toContain('session=1');
});

test('a stream records whole and replays event by event', function () {
    $stream = file_get_contents(__DIR__.'/../../src/Testing/Responses/Fixtures/Messages/CreateStreamedResponseFixture.txt');

    $recorded = iterator_to_array(recordedClient(Recorder::cassette($this->cassette), [new Response(200, ['Content-Type' => 'text/event-stream'], $stream)])
        ->messages()->createStreamed(['model' => 'claude-opus-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello']]]), false);

    $replayed = iterator_to_array(recordedClient(Recorder::cassette($this->cassette))
        ->messages()->createStreamed(['model' => 'claude-opus-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello']]]), false);

    expect($recorded)->not->toBe([])
        ->and(array_map(fn ($event): array => $event->toArray(), $replayed))->toBe(array_map(fn ($event): array => $event->toArray(), $recorded));
});

test('an error answer replays as the same exception', function () {
    // the error shape of https://platform.claude.com/docs/en/api/errors
    $answer = new Response(404, ['Content-Type' => 'application/json'], (string) json_encode(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'The requested resource could not be found.'], 'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy']));

    expect(fn () => recordedClient(Recorder::cassette($this->cassette), [$answer])->models()->retrieve('claude-opus-5-5'))->toThrow(NotFoundException::class)
        ->and(fn () => recordedClient(Recorder::cassette($this->cassette))->models()->retrieve('claude-opus-5-5'))->toThrow(NotFoundException::class, 'The requested resource could not be found.');
});

test('a binary answer is kept as base64 and replays byte for byte', function () {
    // composed: a downloaded file's bytes
    $bytes = "\x89PNG\r\n\x1a\n\x00\xff\xfe";

    expect(recordedClient(Recorder::cassette($this->cassette), [new Response(200, ['Content-Type' => 'image/png'], $bytes)])->files()->download('file_011CNha8iCJcU1wXNR6q4V8w'))->toBe($bytes)
        ->and((string) file_get_contents($this->cassette))->toContain('"base64"')
        ->and(recordedClient(Recorder::cassette($this->cassette))->files()->download('file_011CNha8iCJcU1wXNR6q4V8w'))->toBe($bytes);
});

test('a retried request records as two entries and replays the same way', function () {
    $client = fn (Recorder $recorder, array $answers = []): Client => Anthropic::factory()
        ->withApiKey('sk-ant-recorder-key')
        ->withHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler($answers))]))
        ->withMaxRetries(1)
        ->withRetrySleep(fn (int $milliseconds) => null)
        ->withMiddleware($recorder)
        ->make();

    $client(Recorder::cassette($this->cassette), [
        // the overloaded error of https://platform.claude.com/docs/en/api/errors; composed: its body
        new Response(529, ['Content-Type' => 'application/json'], (string) json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']])),
        new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(CreateResponse::fake(['id' => 'msg_after_retry'])->toArray())),
    ])->messages()->create(['model' => 'claude-opus-5-5']);

    expect(substr_count((string) file_get_contents($this->cassette), '"status"'))->toBe(2)
        ->and($client(Recorder::cassette($this->cassette))->messages()->create(['model' => 'claude-opus-5-5'])->id)->toBe('msg_after_retry');
});

test('a refused key isn\'t saved, and replaying a missing cassette with recording off throws', function () {
    expect(fn () => recordedClient(Recorder::cassette($this->cassette), [new Response(401, ['Content-Type' => 'application/json'], '{}')])->models()->list())
        ->toThrow(RuntimeException::class, 'The API refused the key (status 401)')
        ->and(is_file($this->cassette))->toBeFalse()
        ->and(fn () => Recorder::cassette($this->cassette, record: false))->toThrow(RuntimeException::class, "doesn't exist, and recording is off");
});

test('a key refused after an answer leaves the cassette as it was', function () {
    $answer = fn (): Response => new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(CreateResponse::fake()->toArray()));
    $refused = new Response(401, ['Content-Type' => 'application/json'], '{}');
    $record = function (Recorder $recorder) use ($answer, $refused): void {
        $client = recordedClient($recorder, [$answer(), $refused]);
        $client->messages()->create(['model' => 'claude-opus-5-5']);
        $client->messages()->create(['model' => 'claude-opus-5-5']);
    };

    expect(fn () => $record(Recorder::cassette($this->cassette)))->toThrow(RuntimeException::class, 'The cassette was left as it was.')
        ->and(is_file($this->cassette))->toBeFalse();

    recordedClient(Recorder::cassette($this->cassette), [$answer()])->messages()->create(['model' => 'claude-opus-5-5']);
    $before = file_get_contents($this->cassette);

    expect(fn () => $record(Recorder::cassette($this->cassette, record: true)))->toThrow(RuntimeException::class)
        ->and(file_get_contents($this->cassette))->toBe($before);
});

test('a JSON body that wouldn\'t encode back the same is kept as text', function () {
    // composed: a results line as JSONL, and a JSON file whose number and escape PHP would write differently
    $jsonl = "{\"custom_id\": \"my-first-request\"}\n";
    $file = '{"a": 1.5e3, "b": "caf\\u00e9"}';

    $recorded = recordedClient(Recorder::cassette($this->cassette), [
        new Response(200, ['Content-Type' => 'application/x-jsonl'], $jsonl),
        new Response(200, ['Content-Type' => 'application/json'], $file),
    ]);

    expect($recorded->request('GET', 'messages/batches/msgbatch_01HkcTjaV5uDC8jWR4ZsDV8d/results')->data)->toBe($jsonl)
        ->and($recorded->files()->download('file_011CNha8iCJcU1wXNR6q4V8w'))->toBe($file);

    $replayed = recordedClient(Recorder::cassette($this->cassette));

    expect($replayed->request('GET', 'messages/batches/msgbatch_01HkcTjaV5uDC8jWR4ZsDV8d/results')->data)->toBe($jsonl)
        ->and($replayed->files()->download('file_011CNha8iCJcU1wXNR6q4V8w'))->toBe($file);
});

test('a replay that differs from the cassette throws, saying to record again', function () {
    recordedClient(Recorder::cassette($this->cassette), [new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(CreateResponse::fake()->toArray()))])
        ->messages()->create(['model' => 'claude-opus-5-5']);

    $client = recordedClient(Recorder::cassette($this->cassette));

    expect(fn () => $client->models()->list())->toThrow(RuntimeException::class, 'Request 1 of the cassette ['.$this->cassette.'] is POST /v1/messages, but the test sent GET /v1/models. Record it again.');

    $client = recordedClient(Recorder::cassette($this->cassette));
    $client->messages()->create(['model' => 'claude-opus-5-5']);

    expect(fn () => $client->messages()->create(['model' => 'claude-opus-5-5']))->toThrow(RuntimeException::class, 'holds 1 requests, and the test sent another: POST /v1/messages. Record it again.');
});

test('record: true records again over an existing cassette', function () {
    recordedClient(Recorder::cassette($this->cassette), [new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(CreateResponse::fake(['id' => 'msg_first'])->toArray()))])
        ->messages()->create(['model' => 'claude-opus-5-5']);
    recordedClient(Recorder::cassette($this->cassette, record: true), [new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(CreateResponse::fake(['id' => 'msg_second'])->toArray()))])
        ->messages()->create(['model' => 'claude-opus-5-5']);

    expect(recordedClient(Recorder::cassette($this->cassette))->messages()->create(['model' => 'claude-opus-5-5'])->id)->toBe('msg_second');
});

test('a file the recorder didn\'t write is refused', function () {
    mkdir(dirname($this->cassette), 0777, true);
    file_put_contents($this->cassette, '{"something": "else"}');

    expect(fn () => Recorder::cassette($this->cassette))->toThrow(RuntimeException::class, "isn't one the recorder wrote");

    // edited by hand: an interaction without its answer, a body that isn't base64, and one that isn't text
    foreach (['{"interactions": [{"request": {"method": "GET", "path": "/v1/models"}}]}', '{"interactions": [{"request": {"method": "GET", "path": "/v1/models"}, "response": {"status": 200, "headers": {}, "base64": "not base64!"}}]}', '{"interactions": [{"request": {"method": "GET", "path": "/v1/models"}, "response": {"status": 200, "headers": {}, "body": {}}}]}'] as $edited) {
        file_put_contents($this->cassette, $edited);

        expect(fn () => Recorder::cassette($this->cassette))->toThrow(RuntimeException::class, "isn't one the recorder wrote");
    }
});
