<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AccountWithdrawPix;

/**
 * Account Withdraw PIX Repository Implementation - FOCUSED ON CASE REQUIREMENTS
 * Simple repository for PIX email key storage
 */
class AccountWithdrawPixRepository implements AccountWithdrawPixRepositoryInterface
{
    /**
     * Create PIX details for withdrawal
     */
    public function create(AccountWithdrawPix $pixDetails): AccountWithdrawPix
    {
        $pixDetails->save();
        return $pixDetails;
    }

    /**
     * Find PIX details by withdrawal ID
     */
    public function findByWithdrawId(string $withdrawId): ?AccountWithdrawPix
    {
        return AccountWithdrawPix::query()
            ->where('account_withdraw_id', $withdrawId)
            ->first();
    }
}