<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

use Psr\Http\Message\ResponseInterface;

final class RateLimitException extends ErrorException
{
    /**
     * Creates a new Exception instance.
     *
     * @param  array{type?: ?string, message?: string|array<int, string>}  $contents
     */
    public function __construct(array $contents, ResponseInterface $response)
    {
        $contents['message'] = ($contents['message'] ?? '') ?: 'Request rate limit has been exceeded.';
        $contents['type'] = $contents['type'] ?? 'rate_limit_error';

        parent::__construct($contents, $response);
    }
}
