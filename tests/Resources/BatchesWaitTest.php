<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Contracts\TransporterContract;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Resources\Batches;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Testing\ClientFake;
use Anthropic\ValueObjects\ApiKey;
use Anthropic\ValueObjects\Transporter\BaseUri;
use Anthropic\ValueObjects\Transporter\Headers;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\QueryParams;
use Anthropic\ValueObjects\Transporter\Response;

/**
 * A client whose batch is retrieved in each of these states, in order.
 *
 * @param  list<string>  $statuses
 */
function pollingClient(array $statuses): Client
{
    $transporter = Mockery::mock(TransporterContract::class);
    $answers = array_map(fn (string $status): Response => Response::from(pollingBatch($status), metaHeaders()), $statuses);

    $transporter->shouldReceive('requestObject')
        ->times(count($statuses))
        ->withArgs(fn (Payload $payload): bool => $payload->toRequest(
            BaseUri::from('api.anthropic.com/v1'),
            Headers::withAuthorization(ApiKey::from('foo')),
            QueryParams::create(),
        )->getUri()->getPath() === '/v1/messages/batches/msgbatch_04Rka1yCsMLGPnR7kfPdgR8x')
        ->andReturn(...$answers);

    return new Client($transporter);
}

/**
 * A batch in a processing status: the fixtures' in-progress and ended ones, and the batch processing guide's canceling
 * one (https://platform.claude.com/docs/en/build-with-claude/batch-processing).
 *
 * @return array<string, mixed>
 */
function pollingBatch(string $status): array
{
    return match ($status) {
        'ended' => batchResponse(),
        'canceling' => [
            ...batchInProgressResponse(),
            'id' => 'msgbatch_013Zva2CMHLNnXjNJJKqJ2EF',
            'processing_status' => 'canceling',
            'request_counts' => ['processing' => 2, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
            'ended_at' => null,
            'created_at' => '2024-09-24T18:37:24.100435Z',
            'expires_at' => '2024-09-25T18:37:24.100435Z',
            'cancel_initiated_at' => '2024-09-24T18:39:03.114875Z',
            'results_url' => null,
        ],
        default => batchInProgressResponse(),
    };
}

test('wait retrieves the batch until it has ended, sleeping the interval between polls', function () {
    $slept = [];

    $batch = pollingClient(['in_progress', 'canceling', 'ended'])->batches()
        ->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', interval: 30, sleep: function (int $seconds) use (&$slept): void {
            $slept[] = $seconds;
        });

    expect($batch->isEnded())->toBeTrue()
        ->and($batch->requestCounts->succeeded)->toBe(95)
        ->and($slept)->toBe([30, 30]);
});

test('wait polls one last time at the timeout and returns the batch as it was then', function (int $timeout, array $slept) {
    $sleeps = [];

    $batch = pollingClient(array_fill(0, count($slept) + 1, 'in_progress'))->batches()
        ->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', interval: 60, timeout: $timeout, sleep: function (int $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        });

    expect($batch->isEnded())->toBeFalse()
        ->and($batch->processingStatus)->toBe('in_progress')
        ->and($sleeps)->toBe($slept);
})->with([
    'a timeout between polls' => [100, [60, 40]],
    'a timeout on a poll' => [120, [60, 60]],
    'a timeout under the interval' => [45, [45]],
]);

test('wait with a timeout of zero retrieves once', function () {
    $batch = pollingClient(['in_progress'])->batches()
        ->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', timeout: 0, sleep: fn (int $seconds) => throw new LogicException('slept'));

    expect($batch->processingStatus)->toBe('in_progress');
});

test('wait refuses an interval under a second and a negative timeout', function () {
    $batches = pollingClient([])->batches();

    expect(fn () => $batches->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', interval: 0))->toThrow(InvalidArgumentException::class, 'at least one second, 0 given')
        ->and(fn () => $batches->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', timeout: -5))->toThrow(InvalidArgumentException::class, "can't be negative, -5 given");
});

test('only an ended batch has ended', function (string $status, bool $ended) {
    expect(BatchResponse::fake(pollingBatch($status))->isEnded())->toBe($ended);
})->with([
    'in progress' => ['in_progress', false],
    'canceling' => ['canceling', false],
    'ended' => ['ended', true],
]);

test('the fake answers each poll from its queue, records it as a retrieve and never sleeps', function () {
    $fake = new ClientFake([BatchResponse::fake(pollingBatch('in_progress')), BatchResponse::fake(pollingBatch('ended'))]);

    expect($fake->batches()->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', interval: 3600)->isEnded())->toBeTrue();

    $fake->assertSentTimes(Batches::class, 2, fn (string $method, string $id): bool => $method === 'retrieve' && $id === 'msgbatch_04Rka1yCsMLGPnR7kfPdgR8x');
});

test('the fake sleeps with a sleep of the test\'s own', function () {
    $slept = [];
    $fake = new ClientFake([BatchResponse::fake(pollingBatch('in_progress')), BatchResponse::fake(pollingBatch('ended'))]);

    $fake->batches()->wait('msgbatch_04Rka1yCsMLGPnR7kfPdgR8x', interval: 30, sleep: function (int $seconds) use (&$slept): void {
        $slept[] = $seconds;
    });

    expect($slept)->toBe([30]);
});

test('the results filters yield one type each, reading the results once', function () {
    $results = [batchIndividualSucceededResponse(), batchIndividualErroredResponse(), batchIndividualCanceledResponse(), batchIndividualExpiredResponse()];
    $ids = fn (Generator $filtered): array => array_map(fn ($individual): string => $individual->customId, iterator_to_array($filtered, false));

    expect($ids(BatchResultResponse::fake($results)->succeeded()))->toBe(['request-1'])
        ->and($ids(BatchResultResponse::fake($results)->errored()))->toBe(['request-2'])
        ->and($ids(BatchResultResponse::fake($results)->canceled()))->toBe(['request-3'])
        ->and($ids(BatchResultResponse::fake($results)->expired()))->toBe(['request-4']);

    $once = BatchResultResponse::fake($results);

    expect($ids($once->errored()))->toBe(['request-2'])
        ->and($ids($once->succeeded()))->toBe([]);
});
