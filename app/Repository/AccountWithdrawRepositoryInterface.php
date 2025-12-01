<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AccountWithdraw;
use Hyperf\Database\Model\Collection;

/**
 * Account Withdraw Repository Interface - FOCUSED ON CASE REQUIREMENTS
 * Only methods needed for withdrawal processing and cron job
 */
interface AccountWithdrawRepositoryInterface
{
    /**
     * Create new withdrawal record
     */
    public function create(AccountWithdraw $withdraw): AccountWithdraw;

    /**
     * Find pending scheduled withdrawals ready to process
     * Used by cron job - CRITICAL for horizontal scaling
     */
    public function findPendingScheduled(int $limit = 50): Collection;

    /**
     * Update withdrawal status atomically to prevent duplicate processing
     * CRITICAL: This prevents race conditions between multiple containers
     */
    public function updateStatusAtomically(string $id, string $fromStatus, string $toStatus): bool;

    /**
     * Mark withdrawal as completed
     */
    public function markAsCompleted(string $id): bool;

    /**
     * Mark withdrawal as rejected with error reason
     */
    public function markAsRejected(string $id, string $errorReason): bool;

    /**
     * Find withdrawal by ID
     */
    public function findById(string $id): ?AccountWithdraw;
}