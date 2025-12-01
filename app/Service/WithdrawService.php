<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\WithdrawRequestDto;
use App\Dto\ProcessResultDto;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Repository\AccountRepositoryInterface;
use App\Repository\AccountWithdrawRepositoryInterface;
use App\Repository\AccountWithdrawPixRepositoryInterface;
use App\Strategy\WithdrawStrategyFactory;
use App\Exception\InsufficientBalanceException;
use App\Exception\ConcurrencyException;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Component;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use App\Event\WithdrawProcessedEvent;

/**
 * Serviço de Saque - LÓGICA DE NEGÓCIO PRINCIPAL COM CONTROLE DE CONCORRÊNCIA
 * Gerencia saques imediatos e agendados com prevenção de condições de corrida
 */
class WithdrawService
{
    #[Inject]
    private AccountRepositoryInterface $accountRepository;

    #[Inject]
    private AccountWithdrawRepositoryInterface $withdrawRepository;

    #[Inject]
    private AccountWithdrawPixRepositoryInterface $pixRepository;

    #[Inject]
    private WithdrawStrategyFactory $strategyFactory;

    #[Inject]
    private LoggerInterface $logger;

    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    /**
     * Processa requisição de saque (imediato ou agendado)
     * CRÍTICO: Usa bloqueio pessimista para prevenir condições de corrida
     */
    public function processWithdraw(string $accountId, WithdrawRequestDto $request): ProcessResultDto
    {
        $this->logger->info('Processing withdrawal request', [
            'account_id' => $accountId,
            'amount' => $request->amount,
            'method' => $request->method,
            'scheduled' => $request->isScheduled()
        ]);

        try {
            // Get withdrawal strategy
            $strategy = $this->strategyFactory->getStrategy($request->method);

            // Validate request using strategy
            $validationResult = $strategy->validate($request);
            if ($validationResult->isInvalid()) {
                return ProcessResultDto::failure(
                    'Validation failed: ' . $validationResult->getFirstError(),
                    'VALIDATION_ERROR',
                    ['validation_errors' => $validationResult->getErrors()]
                );
            }

            // Process within database transaction with pessimistic locking
            return Db::transaction(function () use ($accountId, $request, $strategy) {
                return $this->executeWithdraw($accountId, $request, $strategy);
            });

        } catch (InsufficientBalanceException $e) {
            $this->logger->warning('Withdrawal failed: insufficient balance', [
                'account_id' => $accountId,
                'amount' => $request->amount,
                'error' => $e->getMessage()
            ]);

            return ProcessResultDto::failure('saldo insuficiente', 'INSUFFICIENT_BALANCE');

        } catch (ConcurrencyException $e) {
            $this->logger->error('Withdrawal failed: concurrency issue', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return ProcessResultDto::failure('Concurrent operation detected, please try again', 'CONCURRENCY_ERROR');

        } catch (\Exception $e) {
            $this->logger->error('Withdrawal failed: unexpected error', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ProcessResultDto::failure('Internal server error', 'INTERNAL_ERROR');
        }
    }

    /**
     * Execute withdrawal within transaction with pessimistic locking
     * CRITICAL: This method prevents race conditions
     */
    private function executeWithdraw(string $accountId, WithdrawRequestDto $request, $strategy): ProcessResultDto
    {
        // CRITICAL: Lock account to prevent concurrent modifications
        $account = $this->accountRepository->findByIdForUpdate($accountId);
        
        if (!$account) {
            throw new \InvalidArgumentException('Account not found');
        }

        // Create withdrawal record
        $withdraw = new AccountWithdraw([
            'account_id' => $accountId,
            'method' => $request->method,
            'amount' => $request->amount,
            'scheduled' => $request->isScheduled(),
            'scheduled_for' => $request->scheduledFor,
            'status' => AccountWithdraw::STATUS_PENDING,
        ]);

        $withdraw = $this->withdrawRepository->create($withdraw);

        // Create PIX details
        $pixDetails = new AccountWithdrawPix([
            'account_withdraw_id' => $withdraw->id,
            'type' => AccountWithdrawPix::TYPE_EMAIL,
            'pix_key' => $request->pixKey,
        ]);

        $this->pixRepository->create($pixDetails);

        // If scheduled, just save and return
        if ($request->isScheduled()) {
            $this->logger->info('Withdrawal scheduled successfully', [
                'withdraw_id' => $withdraw->id,
                'scheduled_for' => $request->scheduledFor->format('Y-m-d H:i:s')
            ]);

            return ProcessResultDto::success([
                'withdraw_id' => $withdraw->id,
                'status' => 'scheduled',
                'scheduled_for' => $request->scheduledFor->format('Y-m-d H:i:s')
            ]);
        }

        // Process immediate withdrawal
        return $this->processImmediateWithdraw($account, $withdraw, $strategy);
    }

    /**
     * Process immediate withdrawal with balance validation
     * CRITICAL: Account is already locked by findByIdForUpdate
     */
    private function processImmediateWithdraw(Account $account, AccountWithdraw $withdraw, $strategy): ProcessResultDto
    {
        // Check balance (account is locked, safe to check)
        if (!$account->hasBalance((float) $withdraw->amount)) {
            // Mark as rejected
            $this->withdrawRepository->markAsRejected($withdraw->id, 'saldo insuficiente');
            
            throw new InsufficientBalanceException(
                "Insufficient balance. Available: {$account->balance}, Requested: {$withdraw->amount}"
            );
        }

        // Update withdrawal status to processing
        $this->withdrawRepository->updateStatusAtomically(
            $withdraw->id, 
            AccountWithdraw::STATUS_PENDING, 
            AccountWithdraw::STATUS_PROCESSING
        );

        // Process using strategy
        $processResult = $strategy->process($account, $withdraw);
        
        if ($processResult->isFailure()) {
            // Mark as rejected
            $this->withdrawRepository->markAsRejected($withdraw->id, $processResult->getErrorMessage());
            
            return ProcessResultDto::failure($processResult->errorMessage, 'STRATEGY_PROCESSING_FAILED', [
                'withdraw_id' => $withdraw->id
            ]);
        }

        // Debit account balance
        $newBalance = $account->balance - $withdraw->amount;
        $this->accountRepository->updateBalance($account->id, $newBalance);

        // Mark as completed
        $this->withdrawRepository->markAsCompleted($withdraw->id);

        $this->logger->info('Immediate withdrawal completed successfully', [
            'withdraw_id' => $withdraw->id,
            'account_id' => $account->id,
            'amount' => $withdraw->amount,
            'new_balance' => $newBalance
        ]);

        // Dispatch event for email notification
        $this->eventDispatcher->dispatch(new WithdrawProcessedEvent(
            $withdraw->id,
            $account->id,
            (float) $withdraw->amount,
            $withdraw->pixDetails->pix_key ?? $withdraw->method
        ));

        return ProcessResultDto::success([
            'withdraw_id' => $withdraw->id,
            'status' => 'completed',
            'amount' => $withdraw->amount,
            'new_balance' => $newBalance
        ]);
    }

    /**
     * Process scheduled withdrawal (called by cron job)
     * CRITICAL: Uses same locking mechanism as immediate withdrawals
     */
    public function processScheduledWithdraw(AccountWithdraw $withdraw): ProcessResultDto
    {
        $this->logger->info('Processing scheduled withdrawal', [
            'withdraw_id' => $withdraw->id,
            'account_id' => $withdraw->account_id,
            'amount' => $withdraw->amount
        ]);

        try {
            return Db::transaction(function () use ($withdraw) {
                // CRITICAL: Lock account to prevent race conditions
                $account = $this->accountRepository->findByIdForUpdate($withdraw->account_id);
                
                if (!$account) {
                    throw new \InvalidArgumentException('Account not found');
                }

                // Check balance
                if (!$account->hasBalance((float) $withdraw->amount)) {
                    // Mark as rejected with specific reason from case
                    $this->withdrawRepository->markAsRejected($withdraw->id, 'saldo insuficiente');
                    
                    $this->logger->warning('Scheduled withdrawal rejected: insufficient balance', [
                        'withdraw_id' => $withdraw->id,
                        'available_balance' => $account->balance,
                        'requested_amount' => $withdraw->amount
                    ]);

                    return ProcessResultDto::failure('saldo insuficiente', 'INSUFFICIENT_BALANCE', [
                        'withdraw_id' => $withdraw->id
                    ]);
                }

                // Get strategy and process
                $strategy = $this->strategyFactory->getStrategy($withdraw->method);
                $processResult = $strategy->process($account, $withdraw);
                
                if ($processResult->isFailure()) {
                    $this->withdrawRepository->markAsRejected($withdraw->id, $processResult->getErrorMessage());
                    return $processResult;
                }

                // Debit account balance
                $newBalance = $account->balance - $withdraw->amount;
                $this->accountRepository->updateBalance($account->id, $newBalance);

                // Mark as completed
                $this->withdrawRepository->markAsCompleted($withdraw->id);

                $this->logger->info('Scheduled withdrawal completed successfully', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $account->id,
                    'amount' => $withdraw->amount,
                    'new_balance' => $newBalance
                ]);

                // Dispatch event for email notification
                $this->eventDispatcher->dispatch(new WithdrawProcessedEvent(
                    $withdraw->id,
                    $account->id,
                    (float) $withdraw->amount,
                    $withdraw->pixDetails->pix_key ?? $withdraw->method
                ));

                return ProcessResultDto::success([
                    'withdraw_id' => $withdraw->id,
                    'status' => 'completed',
                    'amount' => $withdraw->amount,
                    'new_balance' => $newBalance
                ]);
            });

        } catch (\Exception $e) {
            $this->logger->error('Scheduled withdrawal failed', [
                'withdraw_id' => $withdraw->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark as rejected
            $this->withdrawRepository->markAsRejected($withdraw->id, 'Processing error: ' . $e->getMessage());

            return ProcessResultDto::failure('Processing error', 'PROCESSING_ERROR', [
                'withdraw_id' => $withdraw->id
            ]);
        }
    }
}