<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 409 (`conflict_error`): the request conflicts with the resource's current state.
 */
class ConflictException extends ErrorException {}
