<?php

use Anthropic\Responses\Edits\CreateResponse;
use Anthropic\Responses\Edits\CreateResponseChoice;
use Anthropic\Responses\Edits\CreateResponseUsage;
use Anthropic\Responses\Meta\MetaInformation;

test('create', function () {
    $client = mockClient('POST', 'edits', [
        'model' => 'text-davinci-edit-001',
        'input' => 'What day of the wek is it?',
        'instruction' => 'Fix the spelling mistakes',
    ], \Anthropic\ValueObjects\Transporter\Response::from(edit(), metaHeaders()));

    $result = $client->edits()->create([
        'model' => 'text-davinci-edit-001',
        'input' => 'What day of the wek is it?',
        'instruction' => 'Fix the spelling mistakes',
    ]);

    expect($result)
        ->toBeInstanceOf(CreateResponse::class)
        ->object->toBe('edit')
        ->created->toBe(1664135921)
        ->choices->toBeArray()->toHaveCount(1)
        ->choices->each->toBeInstanceOf(CreateResponseChoice::class)
        ->usage->toBeInstanceOf(CreateResponseUsage::class);

    expect($result->choices[0])
        ->text->toBe("What day of the week is it?\n")
        ->index->toBe(0);

    expect($result->usage)
        ->promptTokens->toBe(25)
        ->completionTokens->toBe(28)
        ->totalTokens->toBe(53);

    expect($result->meta())
        ->toBeInstanceOf(MetaInformation::class);
});
