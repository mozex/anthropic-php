<?php

declare(strict_types=1);

namespace Anthropic\Testing\Exceptions\Concerns;

use Anthropic\Exceptions\ConnectionException;
use Anthropic\Exceptions\TimeoutException;
use Http\Discovery\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

trait FakeableTransportFailure
{
    /**
     * A failure as the client throws it when no answer came back: a connection failure or a timeout wraps a PSR-18
     * network exception for a Messages request, any other a plain PSR-18 client exception. The default message reads
     * like cURL's; a subclass of your own fakes as its parent.
     */
    public static function fake(?string $message = null): static
    {
        $network = is_a(static::class, ConnectionException::class, true);
        $message ??= match (true) {
            is_a(static::class, TimeoutException::class, true) => 'cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received',
            $network => 'cURL error 7: Failed to connect to api.anthropic.com port 443: Connection refused',
            default => 'The request to the API failed.',
        };

        if (! $network) {
            return new static(new class($message) extends RuntimeException implements ClientExceptionInterface {});
        }

        $request = (new Psr17Factory)->createRequest('POST', 'https://api.anthropic.com/v1/messages');

        return new static(new class($message, $request) extends RuntimeException implements NetworkExceptionInterface
        {
            public function __construct(string $message, private readonly RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        });
    }
}
