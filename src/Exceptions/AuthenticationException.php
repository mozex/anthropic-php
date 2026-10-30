<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 401 (`authentication_error`): the API key or token is missing or invalid.
 */
class AuthenticationException extends ErrorException {}
