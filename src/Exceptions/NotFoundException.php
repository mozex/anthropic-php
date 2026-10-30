<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 404 (`not_found_error`): the resource, or the model, doesn't exist.
 */
class NotFoundException extends ErrorException {}
