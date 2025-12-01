<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Withdraw Processed Event - FOCUSED ON CASE REQUIREMENTS
 * Triggered when a withdrawal is successfully processed
 */
class WithdrawProcessedEvent
{
    public function __construct(
        public readonly string $withdrawId,
        public readonly string $accountId,
        public readonly float $amount,
        public readonly string $pixKey
    ) {}
}