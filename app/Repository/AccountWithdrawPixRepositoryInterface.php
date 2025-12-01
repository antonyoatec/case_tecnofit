<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AccountWithdrawPix;

/**
 * Account Withdraw PIX Repository Interface - FOCUSED ON CASE REQUIREMENTS
 * Simple interface for PIX email key storage
 */
interface AccountWithdrawPixRepositoryInterface
{
    /**
     * Create PIX details for withdrawal
     */
    public function create(AccountWithdrawPix $pixDetails): AccountWithdrawPix;

    /**
     * Find PIX details by withdrawal ID
     */
    public function findByWithdrawId(string $withdrawId): ?AccountWithdrawPix;
}