<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Insufficient Balance Exception - FOCUSED ON CASE REQUIREMENTS
 * Thrown when account doesn't have enough balance for withdrawal
 */
class InsufficientBalanceException extends \Exception
{
    public function __construct(string $message = 'saldo insuficiente', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}