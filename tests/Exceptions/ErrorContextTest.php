<?php

declare(strict_types=1);

use Anthropic\Exceptions\ErrorException;
use Anthropic\Exceptions\InternalServerException;
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Exceptions\RateLimitException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * The exception a single queued response makes a Messages call throw.
 */
function errorContextCaught(Response $response): ErrorException
{
    $client = Anthropic::factory()
        ->withApiKey('foo')
        ->withHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([$response]))]))
        ->make();

    try {
        $client->messages()->create(['model' => 'claude-sonnet-5-5', 'max_tokens' => 1024, 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
    } catch (ErrorException $e) {
        return $e;
    }

    throw new RuntimeException('Nothing was thrown.');
}

test('the spend cap\'s context carries its request ID, status, type and error code', function () {
    // the spend-cap 429 of https://platform.claude.com/docs/en/api/rate-limits, which has no retry-after
    $e = errorContextCaught(new Response(429, ['Content-Type' => 'application/json'], '{"type":"error","error":{"type":"rate_limit_error","message":"You have reached your API usage limits: your organization has crossed its monthly API usage threshold, set based on your organization\'s API tier. You will regain access on 2026-09-01 at 00:00 UTC.","details":{"error_code":"enforced_spend_limit_reached"}},"request_id":"req_018EeWyXxfu5pfWkrYcMdjWG"}'));

    expect($e)->toBeInstanceOf(RateLimitException::class)
        ->and($e->context())->toBe([
            'anthropic_request_id' => 'req_018EeWyXxfu5pfWkrYcMdjWG',
            'status' => 429,
            'type' => 'rate_limit_error',
            'error_code' => 'enforced_spend_limit_reached',
        ]);
});

test('a rate limit\'s context carries its retry-after', function () {
    // composed: the error shape of https://platform.claude.com/docs/en/api/errors with its type swapped, and a
    // retry-after header
    $e = errorContextCaught(new Response(429, ['Content-Type' => 'application/json', 'retry-after' => '30'], json_encode([
        'type' => 'error',
        'error' => ['type' => 'rate_limit_error', 'message' => 'The requested resource could not be found.'],
        'request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy',
    ])));

    expect($e->context())->toBe(['anthropic_request_id' => 'req_011CSHoEeqs5C35K2UUqR7Fy', 'status' => 429, 'type' => 'rate_limit_error', 'retry_after' => 30]);
});

test('the context leaves out what the error doesn\'t carry', function () {
    // composed: a proxy's HTML page, with no error object, request ID or retry-after
    $e = errorContextCaught(new Response(502, ['Content-Type' => 'text/html'], '<html><body>Bad gateway</body></html>'));

    expect($e->context())->toBe(['status' => 502]);
});

test('a mid-stream error\'s context has the stream\'s 200 status and the event\'s type', function () {
    // the error event of https://platform.claude.com/docs/en/build-with-claude/streaming; composed: the request-id
    // header, the example of https://platform.claude.com/docs/en/api/errors
    $e = new OverloadedException(['type' => 'overloaded_error', 'message' => 'Overloaded'], new Response(200, ['request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG']));

    expect($e->context())->toBe(['anthropic_request_id' => 'req_018EeWyXxfu5pfWkrYcMdjWG', 'status' => 200, 'type' => 'overloaded_error']);
});

test('the context holds only its five values, never the message or the body', function () {
    // the prefill 400 of https://platform.claude.com/docs/en/api/errors; composed: its request-id header
    $e = errorContextCaught(new Response(400, ['Content-Type' => 'application/json', 'request-id' => 'req_018EeWyXxfu5pfWkrYcMdjWG'], json_encode([
        'type' => 'error',
        'error' => ['type' => 'invalid_request_error', 'message' => 'This model does not support assistant message prefill. The conversation must end with a user message.'],
    ])));

    expect(array_keys($e->context()))->toBe(['anthropic_request_id', 'status', 'type'])
        ->and(json_encode($e->context()))->not->toContain('prefill');
});

test('an error type that isn\'t a string is left out, so reporting the error never fails', function () {
    // composed: a gateway's error body with a numeric type
    $e = ErrorException::forStatus(['type' => 503, 'message' => 'Service unavailable'], new Response(503));

    expect($e)->toBeInstanceOf(InternalServerException::class)
        ->and($e->getErrorType())->toBeNull()
        ->and($e->context())->toBe(['status' => 503]);
});
