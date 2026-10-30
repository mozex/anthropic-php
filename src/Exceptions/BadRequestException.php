<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 400 (`invalid_request_error`): bad parameters or missing fields.
 */
class BadRequestException extends ErrorException {}
