<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 422: the request was well formed but couldn't be processed.
 */
class UnprocessableEntityException extends ErrorException {}
