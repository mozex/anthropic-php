<?php

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Responses\Models\RetrieveResponseCapabilities;
use Anthropic\Responses\Models\RetrieveResponseCapabilitiesContextManagement;
use Anthropic\Responses\Models\RetrieveResponseCapabilitiesEffort;
use Anthropic\Responses\Models\RetrieveResponseCapabilitiesThinking;
use Anthropic\Responses\Models\RetrieveResponseCapabilitiesThinkingTypes;
use Anthropic\Responses\Models\RetrieveResponseCapabilitySupport;

test('from', function () {
    $result = RetrieveResponse::from(modelRetrieve(), meta());

    expect($result)
        ->toBeInstanceOf(RetrieveResponse::class)
        ->id->toBe('claude-sonnet-4-6')
        ->type->toBe('model')
        ->createdAt->toBe('2025-05-14T00:00:00Z')
        ->displayName->toBe('Claude Sonnet 4.6')
        ->maxInputTokens->toBe(200000)
        ->maxTokens->toBe(64000)
        ->capabilities->toBeInstanceOf(RetrieveResponseCapabilities::class)
        ->meta()->toBeInstanceOf(MetaInformation::class);

    expect($result->capabilities)
        ->batch->toBeInstanceOf(RetrieveResponseCapabilitySupport::class)
        ->batch->supported->toBeTrue()
        ->citations->supported->toBeTrue()
        ->codeExecution->supported->toBeTrue()
        ->imageInput->supported->toBeTrue()
        ->pdfInput->supported->toBeTrue()
        ->structuredOutputs->supported->toBeTrue()
        ->contextManagement->toBeInstanceOf(RetrieveResponseCapabilitiesContextManagement::class)
        ->effort->toBeInstanceOf(RetrieveResponseCapabilitiesEffort::class)
        ->thinking->toBeInstanceOf(RetrieveResponseCapabilitiesThinking::class);

    expect($result->capabilities->contextManagement)
        ->supported->toBeTrue()
        ->strategies->toBeArray()->toHaveCount(3)
        ->strategies->toHaveKey('clear_thinking_20251015')
        ->strategies->toHaveKey('clear_tool_uses_20250919')
        ->strategies->toHaveKey('compact_20260112');

    expect($result->capabilities->contextManagement->strategies['clear_thinking_20251015'])
        ->toBeInstanceOf(RetrieveResponseCapabilitySupport::class)
        ->supported->toBeTrue();

    expect($result->capabilities->contextManagement->strategies['clear_tool_uses_20250919']->supported)->toBeTrue();
    expect($result->capabilities->contextManagement->strategies['compact_20260112']->supported)->toBeTrue();

    expect($result->capabilities->effort)
        ->supported->toBeTrue()
        ->low->supported->toBeTrue()
        ->medium->supported->toBeTrue()
        ->high->supported->toBeTrue()
        ->max->supported->toBeTrue();

    expect($result->capabilities->thinking)
        ->supported->toBeTrue()
        ->types->toBeInstanceOf(RetrieveResponseCapabilitiesThinkingTypes::class);

    expect($result->capabilities->thinking->types)
        ->adaptive->supported->toBeTrue()
        ->enabled->supported->toBeTrue();
});

test('as array accessible', function () {
    $result = RetrieveResponse::from(modelRetrieve(), meta());

    expect(isset($result['id']))->toBeTrue();

    expect($result['id'])->toBe('claude-sonnet-4-6');
});

test('to array', function () {
    $result = RetrieveResponse::from(modelRetrieve(), meta());

    expect($result->toArray())
        ->toBeArray()
        ->toBe(modelRetrieve());
});

test('fake', function () {
    $response = RetrieveResponse::fake();

    expect($response)
        ->id->toBe('claude-sonnet-4-6')
        ->type->toBe('model');
});

test('fake with override', function () {
    $response = RetrieveResponse::fake([
        'id' => 'claude-opus-4-5',
        'display_name' => 'Claude Opus 4.5',
    ]);

    expect($response)
        ->id->toBe('claude-opus-4-5')
        ->displayName->toBe('Claude Opus 4.5');
});
