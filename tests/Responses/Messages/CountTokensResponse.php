<?php

use Anthropic\Responses\Messages\CountTokensResponse;
use Anthropic\Responses\Meta\MetaInformation;

test('from', function () {
    $result = CountTokensResponse::from(messagesCountTokens(), meta());

    expect($result)
        ->toBeInstanceOf(CountTokensResponse::class)
        ->inputTokens->toBe(2095)
        ->meta()->toBeInstanceOf(MetaInformation::class);
});

test('as array accessible', function () {
    $result = CountTokensResponse::from(messagesCountTokens(), meta());

    expect(isset($result['input_tokens']))->toBeTrue();

    expect($result['input_tokens'])->toBe(2095);
});

test('to array', function () {
    $result = CountTokensResponse::from(messagesCountTokens(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(messagesCountTokens());
});

test('fake', function () {
    $response = CountTokensResponse::fake();

    expect($response)
        ->inputTokens->toBe(2095);
});

test('fake with override', function () {
    $response = CountTokensResponse::fake([
        'input_tokens' => 500,
    ]);

    expect($response)
        ->inputTokens->toBe(500);
});
