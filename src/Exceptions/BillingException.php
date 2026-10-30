<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * 402 (`billing_error`): a problem with billing or payment information.
 */
class BillingException extends ErrorException {}
