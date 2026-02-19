<?php

declare(strict_types=1);

namespace IRIS\SDK\Exceptions;

/**
 * Base exception for all IRIS SDK errors.
 */
class IRISException extends \Exception
{
    /**
     * Additional error details.
     */
    public ?array $errors = null;

    /**
     * Request ID for debugging with support.
     */
    public ?string $requestId = null;

    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Set validation/field errors.
     */
    public function withErrors(?array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Set the request ID.
     */
    public function withRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * Get a formatted error message for logging.
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();

        if ($this->requestId) {
            $message .= " (Request ID: {$this->requestId})";
        }

        if ($this->errors) {
            $message .= "\nErrors: " . json_encode($this->errors, JSON_PRETTY_PRINT);
        }

        return $message;
    }
}
