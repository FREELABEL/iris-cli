<?php

declare(strict_types=1);

namespace IRIS\SDK\Exceptions;

/**
 * Exception for authentication failures (401/403).
 */
class AuthenticationException extends IRISException
{
    /**
     * Create a new authentication exception.
     */
    public function __construct(string $message = 'Authentication failed', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
