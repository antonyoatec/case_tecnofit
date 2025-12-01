<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Account;
use App\Repository\AccountRepositoryInterface;
use Hyperf\Di\Annotation\Component;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * Account Service - FOCUSED ON CASE REQUIREMENTS
 * Simple service for account operations
 */
class AccountService
{
    #[Inject]
    private AccountRepositoryInterface $accountRepository;

    #[Inject]
    private LoggerInterface $logger;

    /**
     * Find account by ID
     */
    public function findById(string $id): ?Account
    {
        return $this->accountRepository->findById($id);
    }

    /**
     * Check if account exists and has sufficient balance
     */
    public function hasBalance(string $accountId, float $amount): bool
    {
        $account = $this->accountRepository->findById($accountId);
        
        if (!$account) {
            return false;
        }

        return $account->hasBalance($amount);
    }

    /**
     * Get account balance
     */
    public function getBalance(string $accountId): ?float
    {
        $account = $this->accountRepository->findById($accountId);
        
        return $account?->balance;
    }

    /**
     * Create account (for testing/seeding)
     */
    public function createAccount(array $data): Account
    {
        $account = $this->accountRepository->create($data);
        
        $this->logger->info('Account created', [
            'account_id' => $account->id,
            'name' => $account->name,
            'initial_balance' => $account->balance
        ]);

        return $account;
    }
}