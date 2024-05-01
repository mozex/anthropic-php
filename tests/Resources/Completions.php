<?php

use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Responses\Completions\CreateResponse;
use Anthropic\Responses\Completions\CreateStreamedResponse;
use Anthropic\Responses\Completions\StreamResponse;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;

test('create', function () {
    $client = mockClient('POST', 'complete', [
        'model' => 'claude-2.1',
        'prompt' => 'hi',
    ], \Anthropic\ValueObjects\Transporter\Response::from(completion(), metaHeaders()));

    $result = $client->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'hi',
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->type->toBe('completion')
        ->id->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB')
        ->completion->toBe(' Hello!')
        ->stop_reason->toBe('stop_sequence')
        ->model->toBe('claude-2.1')
        ->stop->toBe('\n\nHuman:')
        ->log_id->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB');
});

test('create throws an exception if stream option is true', function () {
    Anthropic::client('foo')->completions()->create([
        'model' => 'claude-2.1',
        'prompt' => 'hi',
        'stream' => true,
    ]);
})->expectException(InvalidArgumentException::class);

test('create streamed', function () {
    $response = new Response(
        body: new Stream(completionStream()),
        headers: metaHeaders(),
    );

    $client = mockStreamClient('POST', 'complete', [
        'model' => 'claude-2.1',
        'prompt' => 'hi',
        'stream' => true,
    ], $response);

    $result = $client->completions()->createStreamed([
        'model' => 'claude-2.1',
        'prompt' => 'hi',
    ]);

    expect($result)
        ->toBeInstanceOf(StreamResponse::class)
        ->toBeInstanceOf(IteratorAggregate::class);

    expect($result->getIterator())
        ->toBeInstanceOf(Iterator::class);

    expect($result->getIterator()->current())
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('completion')
        ->id->toBe('compl_01GS5bBdxpspiwnHYoCVk9Di')
        ->completion->toBe(' AI')
        ->stop_reason->toBeNull()
        ->model->toBe('claude-2.1')
        ->stop->toBeNull()
        ->log_id->toBe('compl_01GS5bBdxpspiwnHYoCVk9Di');
});
