<?php

declare(strict_types=1);

namespace Anthropic\Exceptions;

/**
 * The request never got a response: what the HTTP client reports as a network failure, such as DNS resolution,
 * a refused or reset connection, or a failed TLS handshake.
 */
class ConnectionException extends TransporterException {}
