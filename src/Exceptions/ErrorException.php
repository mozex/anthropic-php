<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Http\Message\ResponseInterface;

class ErrorException extends Exception
{
    /**
     * @var array{type?: ?string, message?: string|array<int, string>, details?: mixed, request_id?: ?string}
     */
    private readonly array $contents;

    private readonly int $statusCode;

    public readonly ?ResponseInterface $response;

    /**
     * Creates a new Exception instance.
     *
     * @param  array{type?: ?string, message?: string|array<int, string>, details?: mixed, request_id?: ?string}|string  $contents
     */
    public function __construct(string|array $contents, ResponseInterface|int $response)
    {
        if (is_string($contents)) {
            $contents = ['message' => $contents];
        }

        $this->contents = $contents;

        if ($response instanceof ResponseInterface) {
            $this->response = $response;
            $this->statusCode = $response->getStatusCode();
        } else {
            $this->response = null;
            $this->statusCode = $response;
        }

        $message = ($contents['message'] ?? '') ?: 'Unknown error';

        if (is_array($message)) {
            $message = implode(PHP_EOL, $message);
        }

        parent::__construct($message);
    }

    /**
     * Creates the exception for an error response's HTTP status.
     *
     * @internal
     *
     * @param  array{type?: ?string, message?: string|array<int, string>, details?: mixed, request_id?: ?string}|string  $contents
     */
    public static function forStatus(string|array $contents, ResponseInterface $response): self
    {
        $status = $response->getStatusCode();

        return match (true) {
            $status === 400 => new BadRequestException($contents, $response),
            $status === 401 => new AuthenticationException($contents, $response),
            $status === 402 => new BillingException($contents, $response),
            $status === 403 => new PermissionDeniedException($contents, $response),
            $status === 404 => new NotFoundException($contents, $response),
            $status === 409 => new ConflictException($contents, $response),
            $status === 413 => new RequestTooLargeException($contents, $response),
            $status === 422 => new UnprocessableEntityException($contents, $response),
            // Without the API's error, RateLimitException's own message beats a proxy page's first line.
            $status === 429 => new RateLimitException(is_array($contents) ? $contents : [], $response),
            $status === 504 => new ApiTimeoutException($contents, $response),
            $status === 529 => new OverloadedException($contents, $response),
            $status >= 500 => new InternalServerException($contents, $response),
            default => new self($contents, $response),
        };
    }

    /**
     * Creates the exception for an error that arrives inside a stream, whose HTTP status is 200, from its error type.
     *
     * @internal
     *
     * @param  array{type?: ?string, message?: string|array<int, string>, details?: mixed, request_id?: ?string}  $contents
     */
    public static function forType(array $contents, ResponseInterface $response): self
    {
        return match ($contents['type'] ?? null) {
            'invalid_request_error' => new BadRequestException($contents, $response),
            'authentication_error' => new AuthenticationException($contents, $response),
            'billing_error' => new BillingException($contents, $response),
            'permission_error' => new PermissionDeniedException($contents, $response),
            'not_found_error' => new NotFoundException($contents, $response),
            'conflict_error' => new ConflictException($contents, $response),
            'request_too_large' => new RequestTooLargeException($contents, $response),
            'rate_limit_error' => new RateLimitException($contents, $response),
            'timeout_error' => new ApiTimeoutException($contents, $response),
            'overloaded_error' => new OverloadedException($contents, $response),
            'api_error' => new InternalServerException($contents, $response),
            default => new self($contents, $response),
        };
    }

    /**
     * Returns the HTTP status code.
     *
     * **Note: For streamed requests it might be 200 even in case of an error.**
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns the error message.
     */
    public function getErrorMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * Returns the error type.
     */
    public function getErrorType(): ?string
    {
        return $this->contents['type'] ?? null;
    }

    /**
     * Returns the ID of the request that failed: the error body's `request_id`, else the `request-id` header.
     */
    public function getRequestId(): ?string
    {
        $requestId = $this->contents['request_id'] ?? null;

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        $header = $this->response?->getHeaderLine('request-id') ?? '';

        return $header !== '' ? $header : null;
    }

    /**
     * Returns the error's `details` object, such as `['error_code' => 'enforced_spend_limit_reached']`.
     *
     * @return array<array-key, mixed>|null
     */
    public function getErrorDetails(): ?array
    {
        $details = $this->contents['details'] ?? null;

        return is_array($details) ? $details : null;
    }

    /**
     * Returns how many seconds the `retry-after` header asks to wait, from whole seconds or an HTTP date.
     */
    public function getRetryAfter(): ?int
    {
        $value = trim($this->response?->getHeaderLine('retry-after') ?? '');

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        $date = $this->httpDate($value);

        return $date === null ? null : max(0, $date->getTimestamp() - time());
    }

    /**
     * Reads an HTTP date in the IMF-fixdate, RFC 850 or asctime form, rejecting one PHP would only normalize
     * (a wrong weekday, the 31st of February).
     */
    private function httpDate(string $value): ?DateTimeImmutable
    {
        $value = (string) preg_replace('/ +/', ' ', $value);

        foreach (['D, d M Y H:i:s \G\M\T', 'l, d-M-y H:i:s \G\M\T', 'D M j H:i:s Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));

            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
