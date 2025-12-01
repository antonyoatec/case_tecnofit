<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Dto\WithdrawRequestDto;
use App\Dto\ValidationResultDto;
use App\Dto\ProcessResultDto;
use App\Model\Account;
use App\Model\AccountWithdraw;

/**
 * Withdrawal Method Strategy Interface - FOCUSED ON CASE REQUIREMENTS
 * Defines contract for different withdrawal methods (PIX only for now)
 */
interface WithdrawMethodInterface
{
    /**
     * Check if this strategy supports the given method
     */
    public function supports(string $method): bool;

    /**
     * Validate withdrawal request for this method
     */
    public function validate(WithdrawRequestDto $request): ValidationResultDto;

    /**
     * Process the withdrawal using this method
     */
    public function process(Account $account, AccountWithdraw $withdraw): ProcessResultDto;

    /**
     * Get required fields for this withdrawal method
     */
    public function getRequiredFields(): array;
}