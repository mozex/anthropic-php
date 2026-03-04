<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

use Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;

final class UnserializableResponse extends Exception
{
    /**
     * Creates a new Exception instance.
     */
    public function __construct(JsonException $exception, public readonly ?ResponseInterface $response = null)
    {
        parent::__construct($exception->getMessage(), 0, $exception);
    }
}
