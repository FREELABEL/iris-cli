<?php

declare(strict_types=1);

namespace IRIS\SDK\Exceptions;

/**
 * Exception for validation errors (422).
 */
class ValidationException extends IRISException
{
    /**
     * Field-specific validation errors.
     *
     * @var array<string, array<string>>
     */
    public array $validationErrors = [];

    /**
     * Create a new validation exception.
     *
     * @param string $message Error message
     * @param array|null $errors Field-specific errors
     */
    public function __construct(string $message = 'Validation failed', ?array $errors = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 422, $previous);

        if ($errors) {
            $this->validationErrors = $errors;
            $this->errors = $errors;
        }
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->validationErrors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors.
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->validationErrors[$field]) && !empty($this->validationErrors[$field]);
    }

    /**
     * Get all field names with errors.
     *
     * @return array<string>
     */
    public function getErrorFields(): array
    {
        return array_keys($this->validationErrors);
    }

    /**
     * Get a flat list of all error messages.
     *
     * @return array<string>
     */
    public function getAllMessages(): array
    {
        $messages = [];

        foreach ($this->validationErrors as $field => $errors) {
            foreach ($errors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }

        return $messages;
    }
}
