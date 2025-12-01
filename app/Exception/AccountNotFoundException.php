<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Account Not Found Exception - FOCUSED ON CASE REQUIREMENTS
 * Thrown when account ID doesn't exist in database
 */
class AccountNotFoundException extends \Exception
{
    public function __construct(string $message = 'Account not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}