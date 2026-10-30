<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * The HTTP client gave up waiting for the connection or the response.
 */
class TimeoutException extends ConnectionException {}
