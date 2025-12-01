<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Account;
use Hyperf\DbConnection\Db;

/**
 * Account Repository Implementation - FOCUSED ON CASE REQUIREMENTS
 * Handles account operations with proper transaction management
 */
class AccountRepository implements AccountRepositoryInterface
{
    /**
     * Find account by ID with pessimistic locking
     * CRITICAL: Uses SELECT ... FOR UPDATE to prevent race conditions
     */
    public function findByIdForUpdate(string $id): ?Account
    {
        return Account::query()
            ->where('id', $id)
            ->lockForUpdate()  // SELECT ... FOR UPDATE
            ->first();
    }

    /**
     * Find account by ID (read-only)
     */
    public function findById(string $id): ?Account
    {
        return Account::find($id);
    }

    /**
     * Update account balance atomically
     * Used after successful withdrawal processing
     */
    public function updateBalance(string $id, float $newBalance): bool
    {
        $affected = Account::query()
            ->where('id', $id)
            ->update(['balance' => $newBalance]);

        return $affected > 0;
    }

    /**
     * Create new account
     */
    public function create(array $data): Account
    {
        return Account::create($data);
    }
}