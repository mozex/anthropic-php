<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

use Exception;

/**
 * Thrown by a middleware to have the client send the request again, within its retry limit. Once the retries run out,
 * the exception reaches the caller.
 */
class RetryableException extends Exception {}
