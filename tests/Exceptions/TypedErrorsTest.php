<?php

declare(strict_types=1);

use Anthropic\Client;
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
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function typedErrorsClient(Response|Throwable ...$queue): Client
{
    return Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler($queue))]))
        ->make();
}

function typedErrorsMessage(): array
{
    return ['model' => 'claude-sonnet-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello']]];
}

function typedErrorsCaught(callable $call): Throwable
{
    try {
        $call();
    } catch (Throwable $e) {
        return $e;
    }

    throw new RuntimeException('Nothing was thrown.');
}

/**
 * The error JSON of https://platform.claude.com/docs/en/api/errors, with its status and type swapped per case.
 */
function typedErrorsBody(string $type): string
{
    return json_encode([
        'type' => 'error',
        'error' => ['type' => $type, 'message' => 'The requested resource could not be found.'],
        'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
    ]);
}

test('each error status throws its own subclass of ErrorException', function (int $status, string $type, string $class) {
    $client = typedErrorsClient(new Response($status, ['Content-Type' => 'application/json'], typedErrorsBody($type)));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e)->toBeInstanceOf(ErrorException::class)
        ->and($e::class)->toBe($class)
        ->and($e->getStatusCode())->toBe($status)
        ->and($e->getErrorType())->toBe($type);
})->with([
    '400' => [400, 'invalid_request_error', BadRequestException::class],
    '401' => [401, 'authentication_error', AuthenticationException::class],
    '402' => [402, 'billing_error', BillingException::class],
    '403' => [403, 'permission_error', PermissionDeniedException::class],
    '404' => [404, 'not_found_error', NotFoundException::class],
    '409' => [409, 'conflict_error', ConflictException::class],
    '413' => [413, 'request_too_large', RequestTooLargeException::class],
    // composed: no docs example names a type for 422
    '422' => [422, 'invalid_request_error', UnprocessableEntityException::class],
    '429' => [429, 'rate_limit_error', RateLimitException::class],
    '500' => [500, 'api_error', InternalServerException::class],
    // composed: 502 and 503 come from proxies, the API documents 500, 504 and 529
    '502' => [502, 'api_error', InternalServerException::class],
    '503' => [503, 'api_error', InternalServerException::class],
    '504' => [504, 'timeout_error', ApiTimeoutException::class],
    '529' => [529, 'overloaded_error', OverloadedException::class],
    // composed: statuses without a subclass stay ErrorException
    '405' => [405, 'invalid_request_error', ErrorException::class],
    '408' => [408, 'timeout_error', ErrorException::class],
]);

test('a streamed request that fails before the stream starts throws by status too', function () {
    $client = typedErrorsClient(new Response(529, ['Content-Type' => 'application/json'], typedErrorsBody('overloaded_error')));

    $e = typedErrorsCaught(fn () => $client->messages()->createStreamed(typedErrorsMessage()));

    expect($e)->toBeInstanceOf(OverloadedException::class)
        ->and($e->getStatusCode())->toBe(529);
});

test('an error page that is not the API json throws by status', function () {
    // composed: a proxy's page, no docs example
    $client = typedErrorsClient(new Response(502, ['Content-Type' => 'text/html'], '<html><title>502 Bad Gateway</title></html>'));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e)->toBeInstanceOf(InternalServerException::class)
        ->and($e->getErrorType())->toBeNull()
        ->and($e->getMessage())->toBe('502 Bad Gateway');
});

test('an error in the middle of a stream throws the subclass of its type', function (string $type, string $class) {
    // the streaming page's message_start and error events; the error's type swapped per case (composed)
    $events = 'event: message_start'."\n".'data: {"type": "message_start", "message": {"id": "msg_1nZdL29xx5MUA1yADyHTEsnR8uuvGzszyY", "type": "message", "role": "assistant", "content": [], "model": "claude-sonnet-4-6", "stop_reason": null, "stop_sequence": null, "usage": {"input_tokens": 25, "output_tokens": 1}}}'
        ."\n\nevent: error\ndata: ".json_encode(['type' => 'error', 'error' => ['type' => $type, 'message' => 'Overloaded']])."\n\n";

    $client = typedErrorsClient(new Response(200, ['Content-Type' => 'text/event-stream'], $events));

    $e = typedErrorsCaught(fn () => iterator_to_array($client->messages()->createStreamed(typedErrorsMessage())));

    expect($e::class)->toBe($class)
        ->and($e->getStatusCode())->toBe(200)
        ->and($e->getErrorType())->toBe($type);
})->with([
    'invalid_request_error' => ['invalid_request_error', BadRequestException::class],
    'authentication_error' => ['authentication_error', AuthenticationException::class],
    'billing_error' => ['billing_error', BillingException::class],
    'permission_error' => ['permission_error', PermissionDeniedException::class],
    'not_found_error' => ['not_found_error', NotFoundException::class],
    'conflict_error' => ['conflict_error', ConflictException::class],
    'request_too_large' => ['request_too_large', RequestTooLargeException::class],
    'rate_limit_error' => ['rate_limit_error', RateLimitException::class],
    'api_error' => ['api_error', InternalServerException::class],
    'timeout_error' => ['timeout_error', ApiTimeoutException::class],
    'overloaded_error' => ['overloaded_error', OverloadedException::class],
    // composed: a type this release doesn't know
    'unknown' => ['some_future_error', ErrorException::class],
]);

test('the existing catch blocks still catch every subclass', function () {
    $client = typedErrorsClient(
        new Response(529, ['Content-Type' => 'application/json'], typedErrorsBody('overloaded_error')),
        new Response(429, ['Content-Type' => 'application/json'], typedErrorsBody('rate_limit_error')),
    );

    expect(fn () => $client->messages()->create(typedErrorsMessage()))->toThrow(ErrorException::class)
        ->and(fn () => $client->messages()->create(typedErrorsMessage()))->toThrow(RateLimitException::class);
});

test('a non-json 429 keeps the rate limit exception default message', function () {
    // composed: a proxy's 429 without the API's error object
    $client = typedErrorsClient(new Response(429, ['Content-Type' => 'text/html'], '<html><title>Too many requests</title></html>'));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e)->toBeInstanceOf(RateLimitException::class)
        ->and($e->getMessage())->toBe('Request rate limit has been exceeded.')
        ->and($e->getErrorType())->toBe('rate_limit_error');
});

test('the request id comes from the error body, then the request-id header', function () {
    $client = typedErrorsClient(
        new Response(404, ['Content-Type' => 'application/json', 'request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'], typedErrorsBody('not_found_error')),
        // composed: the errors page's example without its request_id
        new Response(404, ['Content-Type' => 'application/json', 'request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'], json_encode([
            'type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'The requested resource could not be found.'],
        ])),
        new Response(404, ['Content-Type' => 'application/json'], json_encode([
            'type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'The requested resource could not be found.'],
        ])),
    );

    expect(typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()))->getRequestId())->toBe('req_011CSHoEeqs5C35K2UUqR7Fy')
        ->and(typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()))->getRequestId())->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        ->and(typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()))->getRequestId())->toBeNull();
});

test('a mid-stream error keeps the request id of its event', function () {
    // composed: no docs example of a streamed error with a request_id
    $events = "event: error\ndata: ".json_encode([
        'type' => 'error',
        'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded'],
        'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
    ])."\n\n";

    $client = typedErrorsClient(new Response(200, ['Content-Type' => 'text/event-stream', 'request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'], $events));

    $e = typedErrorsCaught(fn () => iterator_to_array($client->messages()->createStreamed(typedErrorsMessage())));

    expect($e)->toBeInstanceOf(OverloadedException::class)
        ->and($e->getRequestId())->toBe('req_011CSHoEeqs5C35K2UUqR7Fy');
});

test('a mid-stream error that is a plain string still throws an ErrorException', function () {
    // composed: a gateway's error event, no docs example
    $events = "event: error\ndata: ".json_encode(['type' => 'error', 'error' => 'Gateway overloaded', 'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy'])."\n\n";

    $client = typedErrorsClient(new Response(200, ['Content-Type' => 'text/event-stream'], $events));

    $e = typedErrorsCaught(fn () => iterator_to_array($client->messages()->createStreamed(typedErrorsMessage())));

    expect($e::class)->toBe(ErrorException::class)
        ->and($e->getMessage())->toBe('Gateway overloaded')
        ->and($e->getRequestId())->toBe('req_011CSHoEeqs5C35K2UUqR7Fy');
});

test('a Completions stream error throws the subclass of its type', function () {
    // composed: no docs example of a Completions error event
    $events = "event: completion\ndata: ".json_encode(['type' => 'completion', 'id' => 'compl_01EKm5HZ9y6khqaSZjsX44fS', 'completion' => ' Hello', 'stop_reason' => null, 'model' => 'claude-2.1', 'stop' => null, 'log_id' => 'compl_01EKm5HZ9y6khqaSZjsX44fS'])
        ."\n\nevent: error\ndata: ".json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded'], 'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy'])."\n\n";

    $client = typedErrorsClient(new Response(200, ['Content-Type' => 'text/event-stream'], $events));

    $e = typedErrorsCaught(fn () => iterator_to_array($client->completions()->createStreamed(['model' => 'claude-2.1', 'prompt' => 'Hi', 'max_tokens_to_sample' => 10])));

    expect($e)->toBeInstanceOf(OverloadedException::class)
        ->and($e->getStatusCode())->toBe(200)
        ->and($e->getRequestId())->toBe('req_011CSHoEeqs5C35K2UUqR7Fy');
});

test('the error details carry the spend cap error code', function () {
    // the spend-cap 429 of https://platform.claude.com/docs/en/api/rate-limits
    $client = typedErrorsClient(
        new Response(429, ['Content-Type' => 'application/json'], '{"type":"error","error":{"type":"rate_limit_error","message":"You have reached your API usage limits: your organization has crossed its monthly API usage threshold, set based on your organization\'s API tier. You will regain access on 2026-09-01 at 00:00 UTC.","details":{"error_code":"enforced_spend_limit_reached"}},"request_id":"req_018EeWyXxfu5pfWkrYcMdjWG"}'),
        new Response(404, ['Content-Type' => 'application/json'], typedErrorsBody('not_found_error')),
        // composed: details that aren't an object
        new Response(400, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'x', 'details' => 'text']])),
    );

    $spendCap = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($spendCap)->toBeInstanceOf(RateLimitException::class)
        ->and($spendCap->getErrorDetails())->toBe(['error_code' => 'enforced_spend_limit_reached'])
        ->and($spendCap->getRequestId())->toBe('req_018EeWyXxfu5pfWkrYcMdjWG')
        ->and($spendCap->getRetryAfter())->toBeNull()
        ->and(typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()))->getErrorDetails())->toBeNull()
        ->and(typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()))->getErrorDetails())->toBeNull();
});

test('retry after reads whole seconds and http dates', function (string $value, ?int $expected) {
    $e = new RateLimitException([], new Response(429, ['retry-after' => $value]));

    expect($e->getRetryAfter())->toBe($expected);
})->with([
    'seconds' => ['30', 30],
    'leading zeros' => ['007', 7],
    'a date in the past' => ['Wed, 21 Oct 2015 07:28:00 GMT', 0],
    'an rfc 850 date in the past' => ['Sunday, 06-Nov-94 08:49:37 GMT', 0],
    'an asctime date in the past' => ['Sun Nov  6 08:49:37 1994', 0],
    'a wrong weekday' => ['Mon, 21 Oct 2015 07:28:00 GMT', null],
    'the 31st of february' => ['Tue, 31 Feb 2015 07:28:00 GMT', null],
    'not a number or a date' => ['soon', null],
    'a negative number' => ['-5', null],
    'empty' => ['', null],
]);

test('retry after counts the seconds until an http date in the future', function () {
    $date = gmdate('D, d M Y H:i:s \G\M\T', time() + 60);

    $retryAfter = (new RateLimitException([], new Response(429, ['retry-after' => $date])))->getRetryAfter();

    expect($retryAfter)->toBeGreaterThanOrEqual(58)->toBeLessThanOrEqual(60);
});

test('an exception built without a response has no request id or retry after', function () {
    $e = new ErrorException(['message' => 'Overloaded', 'type' => 'overloaded_error'], 529);

    expect($e->getRequestId())->toBeNull()
        ->and($e->getRetryAfter())->toBeNull()
        ->and($e->getErrorDetails())->toBeNull();
});

test('a failed connection throws a ConnectionException', function () {
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages');
    $client = typedErrorsClient(new ConnectException('cURL error 7: Failed to connect to api.anthropic.com port 443', $request));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e::class)->toBe(ConnectionException::class)
        ->and($e)->toBeInstanceOf(TransporterException::class)
        ->and($e->getPrevious())->toBeInstanceOf(ConnectException::class);
});

test('a timeout throws a TimeoutException, which is a ConnectionException', function (string $message) {
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages');
    $client = typedErrorsClient(new ConnectException($message, $request));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e::class)->toBe(TimeoutException::class)
        ->and($e)->toBeInstanceOf(ConnectionException::class)
        ->and($e)->toBeInstanceOf(TransporterException::class);
})->with([
    'guzzle' => ['cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received'],
    'guzzle connect' => ['cURL error 28: Connection timed out after 5001 milliseconds'],
    'symfony idle' => ['Idle timeout reached for "https://api.anthropic.com/v1/messages".'],
    'symfony max duration' => ['Max duration was reached for "https://api.anthropic.com/v1/messages".'],
]);

test('a guzzle failure without a response, such as a reset connection, throws a ConnectionException', function (string $message) {
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages');
    $client = typedErrorsClient(new RequestException($message, $request));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e::class)->toBe(ConnectionException::class);
})->with([
    'reset' => ['cURL error 56: Recv failure: Connection reset by peer'],
    'certificate' => ['cURL error 60: SSL certificate problem: unable to get local issuer certificate'],
]);

test('a timeout in the url alone is not a timeout', function () {
    $request = new Request('POST', 'https://llm.example.com/timeout-proxy/v1/messages');
    $client = typedErrorsClient(new ConnectException('cURL error 7: Failed to connect to llm.example.com port 443 after 3 ms: Couldn\'t connect to server (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://llm.example.com/timeout-proxy/v1/messages', $request));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e::class)->toBe(ConnectionException::class);
});

test('any other client failure stays a TransporterException', function () {
    $client = typedErrorsClient(new TransferException('Something went wrong with the transfer'));

    $e = typedErrorsCaught(fn () => $client->messages()->create(typedErrorsMessage()));

    expect($e::class)->toBe(TransporterException::class);
});

test('a streamed connection failure throws a ConnectionException', function () {
    $request = new Request('POST', 'https://api.anthropic.com/v1/messages');
    $client = typedErrorsClient(new ConnectException('cURL error 6: Could not resolve host: api.anthropic.com', $request));

    $e = typedErrorsCaught(fn () => $client->messages()->createStreamed(typedErrorsMessage()));

    expect($e::class)->toBe(ConnectionException::class);
});
