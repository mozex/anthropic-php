<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 413 (`request_too_large`): the request is over the size limit.
 */
class RequestTooLargeException extends ErrorException {}
