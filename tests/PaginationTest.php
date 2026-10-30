<?php

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Resources\Batches;
use Anthropic\Resources\Files;
use Anthropic\Resources\Models;
use Anthropic\Responses\Batches\BatchListResponse;
use Anthropic\Responses\Batches\BatchResponse;
use Anthropic\Responses\Files\FileListResponse;
use Anthropic\Responses\Files\FileResponse;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Testing\ClientFake;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A client over a mocked Guzzle client that answers with the given pages and records every request.
 *
 * @param  array<int, array{request: RequestInterface}>  $history
 * @param  array<int, array<string, mixed>>  $pages
 */
function pagingClient(array &$history, array ...$pages): Client
{
    $stack = HandlerStack::create(new MockHandler(array_map(
        fn (array $page): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode($page)),
        $pages,
    )));
    $stack->push(Middleware::history($history));

    return Anthropic::factory()->withApiKey('foo')->withHttpClient(new GuzzleClient(['handler' => $stack]))->make();
}

/**
 * The fixture's two models as the last page. Composed: the fixture says there's more.
 *
 * @return array<string, mixed>
 */
function pagingLastModels(): array
{
    return [...modelList(), 'has_more' => false];
}

/**
 * @param  array<int, array{request: RequestInterface}>  $history
 * @return list<string>
 */
function pagingQueries(array $history): array
{
    return array_map(fn (array $entry): string => $entry['request']->getUri()->getQuery(), $history);
}

test('models are walked page by page, following last_id', function () {
    $history = [];
    $client = pagingClient($history, json_decode(modelsListDocsExample(), true), pagingLastModels());

    $models = iterator_to_array($client->models()->iterate(['limit' => 1, 'lifecycle' => ['active']]));

    expect(array_keys($models))->toBe([0, 1, 2])
        ->and(array_map(fn (RetrieveResponse $model): string => $model->id, $models))->toBe(['claude-opus-5-5', 'claude-sonnet-4-6', 'claude-haiku-4-5'])
        // the other parameters carry over to every page
        ->and(pagingQueries($history))->toBe(['limit=1&lifecycle%5B%5D=active', 'limit=1&lifecycle%5B%5D=active&after_id=last_id']);
});

test('a walk that starts with before_id goes backwards, following first_id', function () {
    $history = [];
    $client = pagingClient($history, json_decode(modelsListDocsExample(), true), pagingLastModels());

    iterator_to_array($client->models()->iterate(['before_id' => 'claude-haiku-4-5']));

    expect(pagingQueries($history))->toBe(['before_id=claude-haiku-4-5', 'before_id=first_id']);
});

test('batches are walked page by page', function () {
    $history = [];
    // composed: the docs list example with has_more set
    $client = pagingClient($history, [...batchListResponse(), 'has_more' => true], batchListResponse());

    $batches = iterator_to_array($client->batches()->iterate());

    expect($batches)->toHaveCount(4)
        ->each->toBeInstanceOf(BatchResponse::class)
        ->and(pagingQueries($history))->toBe(['', 'after_id=msgbatch_07V2nm5PqB3bP8szLgTmn1EG']);
});

test('files are walked on the id cursor the Files beta answers with', function () {
    $history = [];
    // composed: the list fixture with has_more set
    $client = pagingClient($history, [...fileListResponse(), 'has_more' => true], fileListResponse());

    $files = iterator_to_array($client->files()->iterate());

    expect($files)->toHaveCount(2)
        ->each->toBeInstanceOf(FileResponse::class)
        ->and(pagingQueries($history))->toBe(['', 'after_id=file_011CNha8iCJcU1wXNR6q4V8w'])
        ->and($history[1]['request']->getHeaderLine('anthropic-beta'))->toBe('files-api-2025-04-14');
});

test('a page cursor is followed through next_page', function () {
    $history = [];
    // composed: the last page, whose next_page is null
    $client = pagingClient($history, fileListGaDocsExample(), [...fileListGaDocsExample(), 'next_page' => null]);

    $files = iterator_to_array($client->files()->iterate(['limit' => 1]));

    expect($files)->toHaveCount(2)
        ->and(pagingQueries($history))->toBe(['limit=1', 'limit=1&page=next_page']);
});

test('the walk stops on the last page, an empty page or a missing cursor', function (array $page) {
    $history = [];
    $client = pagingClient($history, $page, pagingLastModels());

    iterator_to_array($client->models()->iterate());

    expect($history)->toHaveCount(1);
})->with([
    'has_more false' => [pagingLastModels()],
    // composed: pages no docs example shows
    'an empty page that says there is more' => [['data' => [], 'has_more' => true, 'first_id' => 'first_id', 'last_id' => 'last_id']],
    'no last_id' => [[...json_decode(modelsListDocsExample(), true), 'last_id' => null]],
]);

test('a cursor equal to the one just sent ends the walk', function (array $parameters, array $page) {
    $history = [];
    $client = pagingClient($history, $page, pagingLastModels());

    iterator_to_array($client->files()->iterate($parameters));

    expect($history)->toHaveCount(1);
})->with([
    // composed: an answer that hands back the cursor it was given
    'after_id' => [['after_id' => 'file_011CNha8iCJcU1wXNR6q4V8w'], [...fileListResponse(), 'has_more' => true]],
    'page' => [['page' => 'next_page'], fileListGaDocsExample()],
]);

test('an empty before_id walks forwards', function () {
    $history = [];
    $client = pagingClient($history, json_decode(modelsListDocsExample(), true), pagingLastModels());

    iterator_to_array($client->models()->iterate(['before_id' => '']));

    expect(pagingQueries($history))->toBe(['before_id=', 'before_id=&after_id=last_id']);
});

test('nothing is requested until the caller loops', function () {
    $history = [];
    $client = pagingClient($history, pagingLastModels());

    $models = $client->models()->iterate();

    expect($history)->toBe([]);

    foreach ($models as $model) {
        break;
    }

    expect($history)->toHaveCount(1);
});

test('the fakes walk their queued pages, recording each as a list request', function (string $resource, Closure $iterate, array $pages) {
    $fake = new ClientFake($pages);

    $items = iterator_to_array($iterate($fake));

    expect($items)->toHaveCount(2);

    $fake->assertSent($resource, 2);
    $fake->assertSent($resource, fn (string $method, array $parameters = []): bool => $method === 'list' && ($parameters['after_id'] ?? null) === 'next_cursor');
})->with([
    // composed: the fixtures with has_more and a cursor set
    'models' => [Models::class, fn (ClientFake $fake) => $fake->models()->iterate(), [ListResponse::fake(['has_more' => true, 'last_id' => 'next_cursor']), ListResponse::fake()]],
    'batches' => [Batches::class, fn (ClientFake $fake) => $fake->batches()->iterate(), [BatchListResponse::fake(['has_more' => true, 'last_id' => 'next_cursor']), BatchListResponse::fake()]],
    'files' => [Files::class, fn (ClientFake $fake) => $fake->files()->iterate(), [FileListResponse::fake(['has_more' => true, 'last_id' => 'next_cursor']), FileListResponse::fake()]],
]);
