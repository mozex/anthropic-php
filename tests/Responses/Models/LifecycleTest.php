<?php

declare(strict_types=1);

use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;

/**
 * A model in the given stage of its life.
 */
function modelInLifecycle(?string $lifecycle, ?string $retiresAt): RetrieveResponse
{
    $attributes = modelRetrieve();

    if ($lifecycle !== null) {
        $attributes['lifecycle'] = $lifecycle;
    }

    if ($retiresAt !== null) {
        $attributes['retires_at'] = $retiresAt;
    }

    return RetrieveResponse::from($attributes, MetaInformation::from([]));
}

test('the retirement date comes from retires_at', function () {
    // the retires_at of the Models API reference's example
    $date = modelInLifecycle('active', '2019-12-27T18:11:19.117Z')->retirementDate();

    expect($date)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($date?->format(DATE_RFC3339_EXTENDED))->toBe('2019-12-27T18:11:19.117+00:00')
        ->and(modelInLifecycle('active', null)->retirementDate())->toBeNull();
});

test('a retires_at that isn\'t an RFC 3339 date gives none', function (string $value) {
    expect(modelInLifecycle('deprecated', $value)->retirementDate())->toBeNull();
})->with([
    // composed: values PHP's date parser would otherwise read as now or a relative date
    'text' => ['soon'],
    'empty' => [''],
    'a relative date' => ['next week'],
]);

test('lifecycle alone says whether a model is retired or retiring, and a past date only makes it overdue', function (?string $lifecycle, ?string $retiresAt, bool $retired, bool $retiring, bool $overdue) {
    $now = new DateTimeImmutable('2026-10-30T00:00:00Z');
    $model = modelInLifecycle($lifecycle, $retiresAt);

    expect($model->isRetired())->toBe($retired)
        ->and($model->isRetiring())->toBe($retiring)
        ->and($model->isRetirementOverdue($now))->toBe($overdue);
})->with([
    // composed: the stages and dates the Models reference describes
    'retired' => ['retired', '2026-01-05T00:00:00Z', true, false, false],
    'deprecated, its date passed' => ['deprecated', '2026-10-01T00:00:00Z', false, true, true],
    'deprecated, its date ahead' => ['deprecated', '2027-02-15T00:00:00Z', false, true, false],
    'deprecated, no date' => ['deprecated', null, false, true, false],
    'active' => ['active', '2019-12-27T18:11:19.117Z', false, false, false],
    'no lifecycle sent' => [null, null, false, false, false],
]);

test('without a time given, overdue is judged against now', function () {
    // composed: dates far in the past and the future
    expect(modelInLifecycle('deprecated', '2000-01-01T00:00:00Z')->isRetirementOverdue())->toBeTrue()
        ->and(modelInLifecycle('deprecated', '2999-01-01T00:00:00Z')->isRetirementOverdue())->toBeFalse();
});

test('a list item has the helpers too', function () {
    $item = ListResponse::from(['data' => [modelRetrieve()], 'first_id' => 'claude-sonnet-4-6', 'last_id' => 'claude-sonnet-4-6', 'has_more' => false], MetaInformation::from([]))->data[0];

    expect($item->isRetired())->toBeFalse()
        ->and($item->retirementDate())->toBeNull();
});
