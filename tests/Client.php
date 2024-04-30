<?php

use Anthropic\Resources\Completions;
use Anthropic\Resources\Models;

it('has models', function () {
    $anthropic = Anthropic::client('foo');

    expect($anthropic->models())->toBeInstanceOf(Models::class);
});

it('has completions', function () {
    $anthropic = Anthropic::client('foo');

    expect($anthropic->completions())->toBeInstanceOf(Completions::class);
});
