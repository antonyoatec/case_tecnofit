<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Account;

/**
 * Account Repository Interface - FOCUSED ON CASE REQUIREMENTS
 * Only methods needed for PIX withdrawal operations
 */
interface AccountRepositoryInterface
{
    /**
     * Find account by ID with pessimistic locking (SELECT ... FOR UPDATE)
     * Critical for preventing race conditions in withdrawal operations
     */
    public function findByIdForUpdate(string $id): ?Account;

    /**
     * Find account by ID (read-only)
     */
    public function findById(string $id): ?Account;

    /**
     * Update account balance atomically
     */
    public function updateBalance(string $id, float $newBalance): bool;

    /**
     * Create new account (for testing/seeding)
     */
    public function create(array $data): Account;
}