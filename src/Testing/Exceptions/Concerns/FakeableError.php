<?php

declare(strict_types=1);

namespace Anthropic\Testing\Exceptions\Concerns;

use Anthropic\Exceptions\ApiTimeoutException;
use Anthropic\Exceptions\AuthenticationException;
use Anthropic\Exceptions\BadRequestException;
use Anthropic\Exceptions\BillingException;
use Anthropic\Exceptions\ConflictException;
use Anthropic\Exceptions\InternalServerException;
use Anthropic\Exceptions\NotFoundException;
use Anthropic\Exceptions\OverloadedException;
use Anthropic\Exceptions\PermissionDeniedException;
use Anthropic\Exceptions\RateLimitException;
use Anthropic\Exceptions\RequestTooLargeException;
use Anthropic\Exceptions\UnprocessableEntityException;
use Http\Discovery\Psr17Factory;

trait FakeableError
{
    /**
     * The status, error type and message each exception fakes by default: the types and their descriptions on
     * https://platform.claude.com/docs/en/api/errors (422 has no type of its own there).
     */
    private const FAKES = [
        BadRequestException::class => [400, 'invalid_request_error', 'There was an issue with the format or content of your request.'],
        AuthenticationException::class => [401, 'authentication_error', 'There’s an issue with your API key.'],
        BillingException::class => [402, 'billing_error', 'There’s an issue with your billing or payment information.'],
        PermissionDeniedException::class => [403, 'permission_error', 'Your API key does not have permission to use the specified resource.'],
        NotFoundException::class => [404, 'not_found_error', 'The requested resource was not found.'],
        ConflictException::class => [409, 'conflict_error', 'The request conflicts with the current state of a resource.'],
        RequestTooLargeException::class => [413, 'request_too_large', 'Request exceeds the maximum allowed number of bytes.'],
        UnprocessableEntityException::class => [422, 'invalid_request_error', 'There was an issue with the format or content of your request.'],
        RateLimitException::class => [429, 'rate_limit_error', 'Your organization has hit a rate limit.'],
        InternalServerException::class => [500, 'api_error', 'An unexpected error has occurred internal to Anthropic’s systems.'],
        ApiTimeoutException::class => [504, 'timeout_error', 'The request timed out while processing.'],
        OverloadedException::class => [529, 'overloaded_error', 'The API is temporarily overloaded.'],
    ];

    /**
     * The request ID of the errors page's examples, in both the `request-id` header and the body, as the API sends it.
     */
    private const FAKE_REQUEST_ID = 'req_018EeWyXxfu5pfWkrYcMdjWG';

    /**
     * An exception as the client throws it for an error answer, with a real PSR-7 response: its status, a JSON error
     * body and a `request-id` header. `$error` goes into the error object (`message`, `details`), `$headers` onto the
     * response (`retry-after`), and `$status` replaces the class's own (200 for an error in the middle of a stream).
     *
     * @param  array<string, mixed>  $error
     * @param  array<string, string|list<string>>  $headers
     */
    public static function fake(array $error = [], array $headers = [], ?int $status = null): static
    {
        [$defaultStatus, $type, $message] = self::fakeDefaults($status);

        // Header names ignore case, so a `Request-Id` counts too, and goes out as the one `request-id`.
        $headers = array_change_key_case($headers, CASE_LOWER);
        $requestId = $error['request_id'] ?? $headers['request-id'] ?? self::FAKE_REQUEST_ID;
        $requestId = is_array($requestId) ? (string) reset($requestId) : (string) $requestId;
        unset($error['request_id']);

        $contents = ['type' => $type, 'message' => $message, ...$error];
        $factory = new Psr17Factory;
        $response = $factory->createResponse($status ?? $defaultStatus)
            ->withBody($factory->createStream((string) json_encode(['type' => 'error', 'error' => $contents, 'request_id' => $requestId])));

        foreach (['Content-Type' => 'application/json', ...$headers, 'request-id' => $requestId] as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return new static([...$contents, 'request_id' => $requestId], $response);
    }

    /**
     * The status, type and message of the class, or of its nearest listed ancestor, so a subclass of your own fakes
     * as its parent. A plain ErrorException goes by the status: the errors page gives other 4xx statuses
     * `invalid_request_error`.
     *
     * @return array{int, string, string}
     */
    private static function fakeDefaults(?int $status): array
    {
        foreach ([static::class, ...array_values(class_parents(static::class))] as $class) {
            if (isset(self::FAKES[$class])) {
                return self::FAKES[$class];
            }
        }

        return $status !== null && $status >= 400 && $status < 500
            ? [$status, 'invalid_request_error', self::FAKES[BadRequestException::class][2]]
            : [500, 'api_error', self::FAKES[InternalServerException::class][2]];
    }
}
