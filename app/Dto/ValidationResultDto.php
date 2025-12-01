<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * DTO for validation results
 * Similar to Java Bean Validation ConstraintViolation
 */
class ValidationResultDto
{
    private array $errors = [];

    public function __construct(
        public readonly bool $isValid = true,
        array $errors = []
    ) {
        $this->errors = $errors;
    }

    /**
     * Create valid result
     */
    public static function valid(): self
    {
        return new self(true);
    }

    /**
     * Create invalid result with errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }

    /**
     * Add validation error
     */
    public function addError(string $field, string $message, ?string $code = null): self
    {
        $this->errors[] = [
            'field' => $field,
            'message' => $message,
            'code' => $code,
        ];

        return new self(false, $this->errors);
    }

    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return $this->isValid && empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function isInvalid(): bool
    {
        return !$this->isValid();
    }

    /**
     * Get all validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for specific field
     */
    public function getFieldErrors(string $field): array
    {
        return array_filter($this->errors, fn($error) => $error['field'] === $field);
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0]['message'] ?? null;
    }

    /**
     * Get all error messages
     */
    public function getErrorMessages(): array
    {
        return array_map(fn($error) => $error['message'], $this->errors);
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
        ];
    }

    /**
     * Merge with another validation result
     */
    public function merge(ValidationResultDto $other): self
    {
        $allErrors = array_merge($this->errors, $other->getErrors());
        $isValid = $this->isValid() && $other->isValid();

        return new self($isValid, $allErrors);
    }
}