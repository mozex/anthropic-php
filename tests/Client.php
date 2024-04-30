<?php

use Anthropic\Resources\Completions;

it('has completions', function () {
    $anthropic = Anthropic::client('foo');

    expect($anthropic->completions())->toBeInstanceOf(Completions::class);
});
