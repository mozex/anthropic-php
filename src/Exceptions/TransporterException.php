<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

use Anthropic\Testing\Exceptions\Concerns\FakeableTransportFailure;
use Exception;
use Psr\Http\Client\ClientExceptionInterface;

class TransporterException extends Exception
{
    use FakeableTransportFailure;

    /**
     * Creates a new Exception instance.
     */
    public function __construct(ClientExceptionInterface $exception)
    {
        parent::__construct($exception->getMessage(), 0, $exception);
    }
}
