<?php

use OpenAI\Resources\Completions;
use OpenAI\Resources\Models;

it('has models', function () {
    $openAI = Anthropic::client('foo');

    expect($openAI->models())->toBeInstanceOf(Models::class);
});

it('has completions', function () {
    $openAI = Anthropic::client('foo');

    expect($openAI->completions())->toBeInstanceOf(Completions::class);
});
