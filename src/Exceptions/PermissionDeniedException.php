<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 403 (`permission_error`): the key has no access to the resource.
 */
class PermissionDeniedException extends ErrorException {}
