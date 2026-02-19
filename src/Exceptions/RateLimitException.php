<?php

declare(strict_types=1);

namespace IRIS\SDK\Exceptions;

/**
 * Exception for rate limit exceeded (429).
 */
class RateLimitException extends IRISException
{
    /**
     * Seconds until rate limit resets.
     */
    public int $retryAfter = 60;

    /**
     * Create a new rate limit exception.
     */
    public function __construct(string $message = 'Rate limit exceeded', int $code = 429, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Check if we should retry after waiting.
     */
    public function shouldRetry(): bool
    {
        return $this->retryAfter > 0 && $this->retryAfter <= 300; // Max 5 minutes
    }

    /**
     * Wait for the rate limit to reset, then execute callback.
     *
     * @param callable $callback Function to execute after waiting
     * @return mixed Result of the callback
     */
    public function waitAndRetry(callable $callback): mixed
    {
        sleep($this->retryAfter);
        return $callback();
    }
}
