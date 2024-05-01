<?php

use Anthropic\Responses\Completions\CreateResponse;

test('from', function () {
    $completion = CreateResponse::from(completion());

    expect($completion)
        ->toBeInstanceOf(CreateResponse::class)
        ->type->toBe('completion')
        ->id->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB')
        ->completion->toBe(' Hello!')
        ->stop_reason->toBe('stop_sequence')
        ->model->toBe('claude-2.1')
        ->stop->toBe('\n\nHuman:')
        ->log_id->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB');
});

test('as array accessible', function () {
    $completion = CreateResponse::from(completion());

    expect($completion['id'])->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB');
});

test('to array', function () {
    $completion = CreateResponse::from(completion());

    expect($completion->toArray())
        ->toBeArray()
        ->toBe(completion());
});

test('fake', function () {
    $response = CreateResponse::fake();

    expect($response)
        ->id->toBe('compl_01Sb5nmX365bQaWJ3jDfSgqB');
});

test('fake with override', function () {
    $response = CreateResponse::fake([
        'id' => 'compl_1234',
        'completion' => 'awesome!',
    ]);

    expect($response)
        ->type->toBe('completion')
        ->id->toBe('compl_1234')
        ->completion->toBe('awesome!');
});

test('fake can not add inexistent properties', function () {
    $response = CreateResponse::fake([
        'id' => 'compl_1234',
        'something' => 'else',
    ]);

    expect($response)
        ->id->toBe('compl_1234')
        ->something->toBeNull();
});
