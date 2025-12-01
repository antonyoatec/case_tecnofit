<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * DTO for process result responses
 * Similar to Java Result<T> or Response wrapper
 */
class ProcessResultDto
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly ?array $metadata = null,
        public readonly ?string $errorCode = null
    ) {}

    /**
     * Create successful result
     */
    public static function success(?array $metadata = null): self
    {
        return new self(
            success: true,
            metadata: $metadata
        );
    }

    /**
     * Create failure result
     */
    public static function failure(string $errorMessage, ?string $errorCode = null, ?array $metadata = null): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            metadata: $metadata,
            errorCode: $errorCode
        );
    }

    /**
     * Create result from exception
     */
    public static function fromException(\Exception $exception, ?array $metadata = null): self
    {
        return new self(
            success: false,
            errorMessage: $exception->getMessage(),
            metadata: $metadata,
            errorCode: get_class($exception)
        );
    }

    /**
     * Check if result is successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if result is failure
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Get metadata value by key
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];

        if ($this->isFailure()) {
            $result['error'] = [
                'message' => $this->errorMessage,
            ];

            if ($this->errorCode) {
                $result['error']['code'] = $this->errorCode;
            }

            if ($this->metadata) {
                $result['error']['details'] = $this->metadata;
            }
        } else {
            if ($this->metadata) {
                $result['data'] = $this->metadata;
            }
        }

        return $result;
    }

    /**
     * Convert to JSON string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}