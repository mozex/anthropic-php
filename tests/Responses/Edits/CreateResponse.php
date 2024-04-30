<?php

use Anthropic\Responses\Edits\CreateResponse;
use Anthropic\Responses\Edits\CreateResponseChoice;
use Anthropic\Responses\Edits\CreateResponseUsage;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $response = CreateResponse::from(edit(), meta());

    expect($response)
        ->toBeInstanceOf(CreateResponse::class)
        ->object->toBe('edit')
        ->created->toBe(1664135921)
        ->choices->toBeArray()->toHaveCount(1)
        ->choices->each->toBeInstanceOf(CreateResponseChoice::class)
        ->usage->toBeInstanceOf(CreateResponseUsage::class)
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('as array accessible', function () {
    $response = CreateResponse::from(edit(), meta());

    expect($response['created'])->toBe(1664135921);
});

test('to array', function () {
    $response = CreateResponse::from(edit(), meta());

    expect($response->toArray())
        ->toBeArray()
        ->toBe(edit());
});

test('fake', function () {
    $response = CreateResponse::fake();

    expect($response)
        ->object->toBe('edit');
});

test('fake with override', function () {
    $response = CreateResponse::fake([
        'choices' => [
            [
                'text' => 'This is awesome!',
            ],
        ],
    ]);

    expect($response->choices[0])
        ->text->toBe('This is awesome!')
        ->index->toBe(0);
});
