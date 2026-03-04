<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

use Exception;
use Psr\Http\Message\ResponseInterface;

class ErrorException extends Exception
{
    /**
     * @var array{type?: ?string, message?: string|array<int, string>}
     */
    private readonly array $contents;

    private readonly int $statusCode;

    public readonly ?ResponseInterface $response;

    /**
     * Creates a new Exception instance.
     *
     * @param  array{type?: ?string, message?: string|array<int, string>}|string  $contents
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
}
