<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 529 (`overloaded_error`): the API is temporarily overloaded.
 */
class OverloadedException extends ErrorException {}
