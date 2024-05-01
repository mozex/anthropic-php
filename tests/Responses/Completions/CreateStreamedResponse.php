<?php

use Anthropic\Responses\Completions\CreateStreamedResponse;

test('from', function () {
    $completion = CreateStreamedResponse::from(completion());

    expect($completion)
        ->toBeInstanceOf(CreateStreamedResponse::class)
        ->type->toBe('completion')
        ->id->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB')
        ->completion->toBe(' Hello!')
        ->stop_reason->toBe('stop_sequence')
        ->model->toBe('claude-2.1')
        ->stop->toBe('\n\nHuman:')
        ->log_id->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB');
});

test('as array accessible', function () {
    $completion = CreateStreamedResponse::from(completion());

    expect($completion['id'])->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB');
});

test('to array', function () {
    $completion = CreateStreamedResponse::from(completion());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(completion());
});

test('fake', function () {
    $response = CreateStreamedResponse::fake();

    expect($response->getIterator()->current())
        ->id->toBe('compl_01U8ZMthep4UrANhFshPMUFK');
});

test('fake with override', function () {
    $response = CreateStreamedResponse::fake(completionStream());

    expect($response->getIterator()->current())
        ->id->toBe('compl_01GS5bBdxpspiwnHYoCVk9Di');
});
