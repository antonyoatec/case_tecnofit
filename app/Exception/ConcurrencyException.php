<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Concurrency Exception - FOCUSED ON CASE REQUIREMENTS
 * Thrown when concurrent operations are detected
 */
class ConcurrencyException extends \Exception
{
    public function __construct(string $message = 'Concurrent operation detected', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}