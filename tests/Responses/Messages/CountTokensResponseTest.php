<?php

declare(strict_types=1);

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

test('the beta context management count round-trips', function () {
    // https://platform.claude.com/docs/en/build-with-claude/context-editing
    $json = <<<'JSON'
        {
          "input_tokens": 25000,
          "context_management": {
            "original_input_tokens": 70000
          }
        }
        JSON;

    $result = CountTokensResponse::from(json_decode($json, true, flags: JSON_THROW_ON_ERROR), meta());

    expect($result)
        ->inputTokens->toBe(25000)
        ->contextManagement->toBe(['original_input_tokens' => 70000])
        ->and(docsExampleJson($result->toArray()))->toBe(docsExampleJson($json));
});

test('a count without context management reads it as null', function () {
    $result = CountTokensResponse::from(messagesCountTokens(), meta());

    expect($result->contextManagement)->toBeNull()
        ->and($result->toArray())->not->toHaveKey('context_management');
});
