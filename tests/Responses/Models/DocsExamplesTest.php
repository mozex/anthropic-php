<?php

declare(strict_types=1);

use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Responses\Models\RetrieveResponseCapabilitiesCompaction;
use Anthropic\Responses\Models\RetrieveResponseCapabilitiesServerTools;
use Anthropic\Testing\Responses\Fixtures\Models\RetrieveResponseFixture;

function modelsDocsExample(string $name): RetrieveResponse
{
    return RetrieveResponse::from(json_decode(modelsDocsExamples()[$name], true, flags: JSON_THROW_ON_ERROR), meta());
}

test('docs examples round-trip through toArray()', function (string $json) {
    $response = RetrieveResponse::from(json_decode($json, true, flags: JSON_THROW_ON_ERROR), meta());

    expect(docsExampleJson($response->toArray()))->toBe(docsExampleJson($json));
})->with(fn (): array => modelsDocsExamples());

test('the list docs example round-trips through toArray()', function () {
    $response = ListResponse::from(json_decode(modelsListDocsExample(), true, flags: JSON_THROW_ON_ERROR), meta());

    expect(docsExampleJson($response->toArray()))->toBe(docsExampleJson(modelsListDocsExample()));
});

test('the lifecycle fields are typed', function () {
    $model = modelsDocsExample('retrieve');

    expect($model)
        ->lifecycle->toBe('active')
        ->line->toBe('opus')
        ->deprecatedAt->toBe('2019-12-27T18:11:19.117Z')
        ->retiresAt->toBe('2019-12-27T18:11:19.117Z')
        ->maxInputTokens->toBe(0)
        ->maxTokens->toBe(0)
        ->allowedFallbackModels->toBeNull();
});

test('the new capabilities are typed', function () {
    $capabilities = modelsDocsExample('retrieve')->capabilities;

    expect($capabilities->serverTools)
        ->toBeInstanceOf(RetrieveResponseCapabilitiesServerTools::class)
        ->supported->toBeTrue()
        ->codeExecution->supported->toBeTrue()
        ->webSearch->supported->toBeTrue()
        ->and($capabilities->effort->xhigh?->supported)->toBeTrue()
        ->and($capabilities->thinking->types->disabled?->supported)->toBeTrue()
        ->and($capabilities->thinking->types->betweenTools?->supported)->toBeTrue()
        ->and($capabilities->compaction)->toBeNull();
});

test('the beta fields are typed', function () {
    $model = modelsDocsExample('retrieve (beta)');

    expect($model->allowedFallbackModels)->toBe(['string'])
        ->and($model->capabilities->compaction)
        ->toBeInstanceOf(RetrieveResponseCapabilitiesCompaction::class)
        ->supported->toBeTrue()
        ->summarize->supported->toBeTrue();
});

test('a model without capabilities or token limits builds', function (array $attributes, array $expected) {
    $model = RetrieveResponse::from($attributes, meta());

    expect($model)
        ->capabilities->toBeNull()
        ->maxInputTokens->toBeNull()
        ->maxTokens->toBeNull()
        ->lifecycle->toBeNull()
        ->line->toBeNull()
        ->and($model->toArray())->toBe($expected);
})->with([
    // composed: no docs example of a model without capabilities
    'sent as null' => [
        ['id' => 'claude-opus-5-5', 'type' => 'model', 'created_at' => '2026-09-21T00:00:00Z', 'display_name' => 'Claude Opus 5.5', 'max_input_tokens' => null, 'max_tokens' => null, 'capabilities' => null],
        ['id' => 'claude-opus-5-5', 'type' => 'model', 'created_at' => '2026-09-21T00:00:00Z', 'display_name' => 'Claude Opus 5.5', 'max_input_tokens' => null, 'max_tokens' => null, 'capabilities' => null],
    ],
    'left out' => [
        ['id' => 'claude-opus-5-5', 'type' => 'model', 'created_at' => '2026-09-21T00:00:00Z', 'display_name' => 'Claude Opus 5.5'],
        ['id' => 'claude-opus-5-5', 'type' => 'model', 'created_at' => '2026-09-21T00:00:00Z', 'display_name' => 'Claude Opus 5.5', 'max_input_tokens' => null, 'max_tokens' => null, 'capabilities' => null],
    ],
]);

test('a capability sent as null builds as null and is kept', function (string $path, Closure $read) {
    $attributes = json_decode(modelsDocsExamples()['retrieve (beta)'], true, flags: JSON_THROW_ON_ERROR);
    // composed: the reference types these as "or null"
    $keys = explode('.', $path);
    $leaf = array_pop($keys);
    $node = &$attributes['capabilities'];

    foreach ($keys as $key) {
        $node = &$node[$key];
    }

    $node[$leaf] = null;
    unset($node);

    $model = RetrieveResponse::from($attributes, meta());

    expect($read($model->capabilities))->toBeNull()
        ->and(docsExampleJson($model->toArray()))->toBe(docsExampleJson($attributes));
})->with([
    'effort.xhigh' => ['effort.xhigh', fn ($capabilities) => $capabilities->effort->xhigh],
    'compaction' => ['compaction', fn ($capabilities) => $capabilities->compaction],
]);

test('a null context management strategy builds and is kept', function () {
    $attributes = json_decode(modelsDocsExamples()['retrieve'], true, flags: JSON_THROW_ON_ERROR);
    // composed: the reference types each strategy as "or null"
    $attributes['capabilities']['context_management']['compact_20260112'] = null;

    $model = RetrieveResponse::from($attributes, meta());

    expect($model->capabilities->contextManagement->strategies)->not->toHaveKey('compact_20260112')
        ->and($model->toArray()['capabilities']['context_management'])->toHaveKey('compact_20260112', null);
});

test('a page without ids builds', function (array $attributes, array $expected) {
    $page = ListResponse::from($attributes, meta());

    expect($page)
        ->data->toBe([])
        ->firstId->toBeNull()
        ->lastId->toBeNull()
        ->and($page->toArray())->toBe($expected);
})->with([
    // composed: no docs example of an empty page
    'sent as null' => [
        ['data' => [], 'first_id' => null, 'last_id' => null, 'has_more' => false],
        ['data' => [], 'first_id' => null, 'last_id' => null, 'has_more' => false],
    ],
    'left out' => [
        ['data' => [], 'has_more' => false],
        ['data' => [], 'has_more' => false, 'first_id' => null, 'last_id' => null],
    ],
]);

test('a model from an older release keeps its toArray()', function () {
    expect(RetrieveResponse::from(modelRetrieve(), meta())->toArray())->toBe(modelRetrieve())
        ->and(RetrieveResponse::fake()->toArray())->toBe(RetrieveResponseFixture::ATTRIBUTES);
});
