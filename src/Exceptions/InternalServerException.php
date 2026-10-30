<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * Any other 5xx (`api_error`): an error on the API's side.
 */
class InternalServerException extends ErrorException {}
