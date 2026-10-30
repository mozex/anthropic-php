<?php

declare(strict_types=1);

use Anthropic\Exceptions\ApiTimeoutException;
use Anthropic\Exceptions\AuthenticationException;
use Anthropic\Exceptions\BadRequestException;
use Anthropic\Exceptions\BillingException;
use Anthropic\Exceptions\ConflictException;
use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\InternalServerException;
use Anthropic\Exceptions\NotFoundException;
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Exceptions\PermissionDeniedException;
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\RequestTooLargeException;
use Anthropic\Exceptions\TimeoutException;
use Anthropic\Exceptions\TransporterException;
use Anthropic\Exceptions\UnprocessableEntityException;
use Anthropic\Resources\Files;
use Anthropic\Resources\Messages;
use Anthropic\Resources\Models;
use Anthropic\Resources\Raw;
use Anthropic\Responses\Batches\BatchResultResponse;
use Anthropic\Responses\Completions\StreamResponse as CompletionsStreamResponse;
use Anthropic\Responses\Messages\CountTokensResponse;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Messages\StreamResponse;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\Responses\Raw\RawResponse;
use Anthropic\Testing\ClientFake;
use Anthropic\Testing\Requests\TestRequest;
use PHPUnit\Framework\ExpectationFailedException;
use Psr\Http\Client\NetworkExceptionInterface;

class FakeToolkitSpendCapReached extends RateLimitException {}

test('assertSentTimes() counts the calls, or only those the callback accepts', function () {
    $fake = new ClientFake([CreateResponse::fake(), CreateResponse::fake(), RetrieveResponse::fake()]);

    $fake->messages()->create(['model' => 'claude-opus-5-5']);
    $fake->messages()->create(['model' => 'claude-sonnet-5-5']);
    $fake->models()->retrieve('claude-opus-5-5');

    $fake->assertSentTimes(Messages::class, 2);
    $fake->assertSentTimes(Messages::class, 1, fn (string $method, array $parameters): bool => $parameters['model'] === 'claude-opus-5-5');
    $fake->messages()->assertSentTimes(2);

    expect(fn () => $fake->assertSentTimes(Models::class, 2))->toThrow(ExpectationFailedException::class, 'sent 1 times instead of 2 times');
});

test('assertSentInOrder() checks every call, in order, by resource, pair or closure', function () {
    $fake = new ClientFake([ListResponse::fake(), CreateResponse::fake(), CountTokensResponse::fake()]);

    $fake->models()->list();
    $fake->messages()->create(['model' => 'claude-opus-5-5']);
    $fake->messages()->countTokens(['model' => 'claude-opus-5-5']);

    $fake->assertSentInOrder([
        Models::class,
        [Messages::class, 'create'],
        fn (TestRequest $request): bool => $request->method() === 'countTokens',
    ]);

    expect(fn () => $fake->assertSentInOrder([Models::class, [Messages::class, 'countTokens'], Messages::class]))
        ->toThrow(ExpectationFailedException::class, 'Request 2 was Messages::create(), not the one expected. The requests sent were: Models::list(), Messages::create(), Messages::countTokens().')
        ->and(fn () => $fake->assertSentInOrder([Models::class]))
        ->toThrow(ExpectationFailedException::class, 'Expected 1 requests, 3 were sent.');
});

test('sent() returns the recorded calls in order, all, by resource or by callback', function () {
    $fake = new ClientFake([RetrieveResponse::fake(), CreateResponse::fake()]);

    $fake->models()->retrieve('claude-opus-5-5');
    $fake->messages()->create(['model' => 'claude-opus-5-5']);

    expect(array_map(fn (TestRequest $request): string => $request->method(), $fake->sent()))->toBe(['retrieve', 'create'])
        ->and($fake->sent(Messages::class))->toHaveCount(1)
        ->and($fake->sent(Messages::class)[0]->args()[0]['model'])->toBe('claude-opus-5-5')
        ->and($fake->sent(Models::class, fn (string $method, string $id): bool => $id === 'claude-haiku-4-5'))->toBe([]);
});

test('a fake given no responses answers each call with its method\'s default fake', function () {
    $fake = new ClientFake;

    expect($fake->messages()->create(['model' => 'claude-opus-5-5']))->toBeInstanceOf(CreateResponse::class)
        ->and($fake->models()->list())->toBeInstanceOf(ListResponse::class)
        ->and($fake->messages()->createStreamed(['model' => 'claude-opus-5-5']))->toBeInstanceOf(StreamResponse::class)
        ->and($fake->files()->download('file_011CNha8iCJcU1wXNR6q4V8w'))->toBe('')
        ->and($fake->request('GET', 'models'))->toBeInstanceOf(RawResponse::class);

    $fake->assertSentTimes(Messages::class, 2);
    $fake->assertSent(Files::class);
    $fake->assertSent(Raw::class);
});

test('preventStrayRequests() makes an unanswered call throw, naming it', function () {
    $fake = (new ClientFake)->preventStrayRequests();

    expect(fn () => $fake->messages()->create(['model' => 'claude-opus-5-5']))
        ->toThrow(RuntimeException::class, 'No fake responses left. Messages::create() has none, and stray requests are prevented');
});

test('once the queued responses run out, the next call throws, naming it', function () {
    $fake = new ClientFake([CreateResponse::fake()]);

    $fake->messages()->create(['model' => 'claude-opus-5-5']);

    expect(fn () => $fake->models()->retrieve('claude-opus-5-5'))
        ->toThrow(RuntimeException::class, 'No fake responses left. Models::retrieve() was the next call');
});

test('a queued response that doesn\'t fit the method throws, naming both', function () {
    $fake = new ClientFake([ListResponse::fake()]);

    expect(fn () => $fake->messages()->create(['model' => 'claude-opus-5-5']))
        ->toThrow(RuntimeException::class, 'The fake response for Messages::create() is Anthropic\Responses\Models\ListResponse, but the method returns Anthropic\Responses\Messages\CreateResponse.');
});

test('respondTo() answers matching calls ahead of the queue, as often as they come', function () {
    $fake = (new ClientFake([CreateResponse::fake(['id' => 'msg_queued'])]))
        ->respondTo([Models::class, 'retrieve'], RetrieveResponse::fake(['id' => 'claude-opus-5-5']))
        ->respondTo(fn (TestRequest $request): bool => ($request->args()[0]['model'] ?? null) === 'claude-haiku-4-5', fn (TestRequest $request) => CreateResponse::fake(['id' => 'msg_haiku']))
        ->respondTo(Files::class, 'file contents');

    expect($fake->models()->retrieve('claude-opus-5-5')->id)->toBe('claude-opus-5-5')
        ->and($fake->models()->retrieve('claude-opus-5-5')->id)->toBe('claude-opus-5-5')
        ->and($fake->messages()->create(['model' => 'claude-haiku-4-5'])->id)->toBe('msg_haiku')
        ->and($fake->messages()->create(['model' => 'claude-opus-5-5'])->id)->toBe('msg_queued')
        ->and($fake->files()->download('file_011CNha8iCJcU1wXNR6q4V8w'))->toBe('file contents')
        // a view's responders are the root's
        ->and($fake->onConnection('support')->models()->retrieve('claude-opus-5-5')->id)->toBe('claude-opus-5-5');
});

test('an error fake carries a real response: its status, type, message, request ID and meta', function () {
    $e = NotFoundException::fake();

    expect($e->getStatusCode())->toBe(404)
        ->and($e->getErrorType())->toBe('not_found_error')
        ->and($e->getMessage())->toBe('The requested resource was not found.')
        ->and($e->getRequestId())->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        ->and($e->response?->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $e->response?->getBody(), true)['error']['type'] ?? null)->toBe('not_found_error')
        ->and(MetaInformation::from($e->response?->getHeaders() ?? [])->requestId)->toBe('req_018EeWyXxfu5pfWkrYcMdjWG');
});

test('an error fake takes the error\'s fields, headers and status', function () {
    $spendCap = RateLimitException::fake(['details' => ['error_code' => 'enforced_spend_limit_reached']]);
    $limited = RateLimitException::fake(headers: ['retry-after' => '30']);
    $midStream = OverloadedException::fake(status: 200);
    $other = ErrorException::fake(['message' => 'Method Not Allowed'], status: 405);

    expect($spendCap->getErrorDetails())->toBe(['error_code' => 'enforced_spend_limit_reached'])
        ->and($spendCap->getRetryAfter())->toBeNull()
        ->and($limited->getRetryAfter())->toBe(30)
        ->and($midStream->getStatusCode())->toBe(200)
        ->and($midStream->getErrorType())->toBe('overloaded_error')
        ->and($other)->toBeInstanceOf(ErrorException::class)
        ->and($other->getStatusCode())->toBe(405)
        ->and($other->getMessage())->toBe('Method Not Allowed');
});

test('every API exception fakes with its own status and type, a subclass as its parent', function (string $class, int $status, string $type) {
    $e = $class::fake();

    expect($e)->toBeInstanceOf($class)
        ->and($e->getStatusCode())->toBe($status)
        ->and($e->getErrorType())->toBe($type);
})->with([
    'BadRequest' => [BadRequestException::class, 400, 'invalid_request_error'],
    'Authentication' => [AuthenticationException::class, 401, 'authentication_error'],
    'Billing' => [BillingException::class, 402, 'billing_error'],
    'PermissionDenied' => [PermissionDeniedException::class, 403, 'permission_error'],
    'Conflict' => [ConflictException::class, 409, 'conflict_error'],
    'RequestTooLarge' => [RequestTooLargeException::class, 413, 'request_too_large'],
    'UnprocessableEntity' => [UnprocessableEntityException::class, 422, 'invalid_request_error'],
    'InternalServer' => [InternalServerException::class, 500, 'api_error'],
    'ApiTimeout' => [ApiTimeoutException::class, 504, 'timeout_error'],
    'a subclass of RateLimitException' => [FakeToolkitSpendCapReached::class, 429, 'rate_limit_error'],
]);

test('a plain ErrorException fakes by its status, and a request ID can be set', function () {
    $other = ErrorException::fake(status: 405);
    $withId = NotFoundException::fake(['request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy']);

    expect($other->getErrorType())->toBe('invalid_request_error')
        ->and(ErrorException::fake()->getStatusCode())->toBe(500)
        ->and($withId->getRequestId())->toBe('req_011CSHoEeqs5C35K2UUqR7Fy')
        ->and($withId->response?->getHeaderLine('request-id'))->toBe('req_011CSHoEeqs5C35K2UUqR7Fy')
        // a header of another spelling counts too
        ->and(NotFoundException::fake(headers: ['Request-Id' => 'req_011CSHoEeqs5C35K2UUqR7Fy'])->getRequestId())->toBe('req_011CSHoEeqs5C35K2UUqR7Fy');
});

test('a call recorded without a return type says it has no default', function () {
    $fake = new ClientFake;

    expect(fn () => $fake->record(new TestRequest(Messages::class, 'create', [])))
        ->toThrow(RuntimeException::class, 'No fake responses left. Messages::create() has none, and its return type gives no default');
});

test('the defaults cover every method, and responders work through a view', function () {
    $fake = new ClientFake;
    $fake->addResponses([]);

    expect($fake->messages()->countTokens(['model' => 'claude-opus-5-5']))->toBeInstanceOf(CountTokensResponse::class)
        ->and($fake->batches()->results('msgbatch_01HkcTjaV5uDC8jWR4ZsDV8d'))->toBeInstanceOf(BatchResultResponse::class)
        ->and($fake->completions()->createStreamed(['model' => 'claude-2.1', 'prompt' => 'Hi', 'max_tokens_to_sample' => 10]))->toBeInstanceOf(CompletionsStreamResponse::class);

    $view = $fake->withOptions(['workspace_id' => 'wrkspc_011CZkZaBF1tNoB5wlCeusgy']);
    $view->respondTo(Models::class, OverloadedException::fake())->preventStrayRequests();

    expect(fn () => $fake->models()->list())->toThrow(OverloadedException::class)
        ->and(fn () => $fake->messages()->create(['model' => 'claude-opus-5-5']))->toThrow(RuntimeException::class, 'stray requests are prevented');
});

test('a queued error fake is thrown by the call', function () {
    $fake = new ClientFake([OverloadedException::fake()]);

    expect(fn () => $fake->messages()->create(['model' => 'claude-opus-5-5']))->toThrow(OverloadedException::class, 'The API is temporarily overloaded.');
});

test('transport failure fakes wrap a PSR-18 network exception', function () {
    $connection = ConnectionException::fake();
    $timeout = TimeoutException::fake();
    $other = TransporterException::fake('The request to the gateway failed.');

    expect($connection->getMessage())->toContain('Connection refused')
        ->and($connection->getPrevious())->toBeInstanceOf(NetworkExceptionInterface::class)
        ->and($timeout)->toBeInstanceOf(ConnectionException::class)
        ->and($timeout->getMessage())->toContain('timed out')
        ->and($other->getMessage())->toBe('The request to the gateway failed.')
        ->and($other->getPrevious())->not->toBeInstanceOf(NetworkExceptionInterface::class);

    $fake = new ClientFake([TimeoutException::fake()]);

    expect(fn () => $fake->messages()->create(['model' => 'claude-opus-5-5']))->toThrow(TimeoutException::class);
});

test('the Pest expectations read the fake', function () {
    $fake = new ClientFake([CreateResponse::fake(), RetrieveResponse::fake()]);

    expect($fake)->toHaveSentNothing();

    $fake->messages()->create(['model' => 'claude-opus-5-5']);
    $fake->onConnection('support')->models()->retrieve('claude-opus-5-5');

    expect($fake)->toHaveSent(Messages::class)
        ->toHaveSent(Messages::class, fn (string $method, array $parameters): bool => $parameters['model'] === 'claude-opus-5-5')
        ->toHaveSentTimes(Messages::class, 1)
        ->toHaveSentOn('support', Models::class)
        ->toHaveSentInOrder([Messages::class, Models::class])
        ->not->toHaveSent(Files::class);
});
