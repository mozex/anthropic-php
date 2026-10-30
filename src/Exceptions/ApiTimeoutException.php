<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 504 (`timeout_error`): the request timed out while the API processed it.
 */
class ApiTimeoutException extends ErrorException {}
