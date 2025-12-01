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
use App\Exception\AccountNotFoundException;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Context\ApplicationContext;
use Psr\Log\LoggerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use App\Event\WithdrawProcessedEvent;

/**
 * Serviço de Saque Imediato - FOCADO NOS REQUISITOS DO CASE
 * Gerencia saques PIX imediatos com validação rigorosa e controle de concorrência
 */
class ImmediateWithdrawService
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
     * Processa saque imediato - FUNCIONALIDADE PRINCIPAL
     * Este é o método principal chamado pelo controlador
     */
    public function processImmediate(string $accountId, WithdrawRequestDto $request): ProcessResultDto
    {
        $this->logger->info('Starting immediate withdrawal processing', [
            'account_id' => $accountId,
            'amount' => $request->amount,
            'pix_key' => $request->pixKey,
            'method' => $request->method
        ]);

        // Valida que é realmente imediato (não agendado)
        if ($request->isScheduled()) {
            return ProcessResultDto::failure('This service only handles immediate withdrawals', 'INVALID_REQUEST_TYPE');
        }

        try {
            // Obtém e valida a estratégia
            $strategy = $this->strategyFactory->getStrategy($request->method);
            
            // Valida requisição usando a estratégia
            $validationResult = $strategy->validate($request);
            
            if ($validationResult->isInvalid()) {
                $this->logger->warning('Immediate withdrawal validation failed', [
                    'account_id' => $accountId,
                    'validation_errors' => $validationResult->getErrors()
                ]);

                return ProcessResultDto::failure(
                    'Validation failed: ' . $validationResult->getFirstError(),
                    'VALIDATION_ERROR',
                    ['validation_errors' => $validationResult->getErrors()]
                );
            }

            // Processa dentro de transação de banco de dados com bloqueio pessimista
            return Db::transaction(function () use ($accountId, $request, $strategy) {
                return $this->executeImmediateWithdraw($accountId, $request, $strategy);
            });

        } catch (AccountNotFoundException $e) {
            $this->logger->error('Account not found for immediate withdrawal', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return ProcessResultDto::failure('Account not found', 'ACCOUNT_NOT_FOUND');

        } catch (InsufficientBalanceException $e) {
            $this->logger->warning('Immediate withdrawal failed: insufficient balance', [
                'account_id' => $accountId,
                'amount' => $request->amount,
                'error' => $e->getMessage()
            ]);

            return ProcessResultDto::failure('saldo insuficiente', 'INSUFFICIENT_BALANCE');

        } catch (\Exception $e) {
            $this->logger->error('Immediate withdrawal failed: unexpected error', [
                'account_id' => $accountId,
                'amount' => $request->amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ProcessResultDto::failure('Internal server error', 'INTERNAL_ERROR');
        }
    }

    /**
     * Executa saque imediato dentro de transação
     * CRÍTICO: Usa bloqueio pessimista para prevenir condições de corrida
     */
    private function executeImmediateWithdraw(string $accountId, WithdrawRequestDto $request, $strategy): ProcessResultDto
    {
        // PASSO 1: Bloqueia conta para prevenir modificações concorrentes
        $account = $this->accountRepository->findByIdForUpdate($accountId);
        
        if (!$account) {
            throw new AccountNotFoundException("Account {$accountId} not found");
        }

        $this->logger->info('Account locked for immediate withdrawal', [
            'account_id' => $accountId,
            'current_balance' => $account->balance,
            'requested_amount' => $request->amount
        ]);

        // PASSO 2: Valida saldo APÓS o bloqueio
        if (!$account->hasBalance($request->amount)) {
            throw new InsufficientBalanceException(
                "Insufficient balance. Available: {$account->balance}, Requested: {$request->amount}"
            );
        }

        // PASSO 3: Cria registro de saque com status PENDING
        $withdraw = new AccountWithdraw([
            'account_id' => $accountId,
            'method' => $request->method,
            'amount' => $request->amount,
            'scheduled' => false,  // Sempre false para imediato
            'scheduled_for' => null,
            'status' => AccountWithdraw::STATUS_PENDING,
        ]);

        $withdraw = $this->withdrawRepository->create($withdraw);

        $this->logger->info('Withdrawal record created', [
            'withdraw_id' => $withdraw->id,
            'status' => $withdraw->status
        ]);

        // PASSO 4: Cria detalhes do PIX
        $pixDetails = new AccountWithdrawPix([
            'account_withdraw_id' => $withdraw->id,
            'type' => AccountWithdrawPix::TYPE_EMAIL,
            'pix_key' => $request->pixKey,
        ]);

        $this->pixRepository->create($pixDetails);

        $this->logger->info('PIX details created', [
            'withdraw_id' => $withdraw->id,
            'pix_key' => $request->pixKey,
            'pix_type' => AccountWithdrawPix::TYPE_EMAIL
        ]);

        // PASSO 5: Atualiza status para PROCESSING
        $statusUpdated = $this->withdrawRepository->updateStatusAtomically(
            $withdraw->id,
            AccountWithdraw::STATUS_PENDING,
            AccountWithdraw::STATUS_PROCESSING
        );

        if (!$statusUpdated) {
            throw new \RuntimeException('Failed to update withdrawal status to PROCESSING');
        }

        // PASSO 6: Processa usando estratégia (processamento PIX)
        $processResult = $strategy->process($account, $withdraw);
        
        if ($processResult->isFailure()) {
            $this->logger->error('Strategy processing failed', [
                'withdraw_id' => $withdraw->id,
                'error' => $processResult->getErrorMessage()
            ]);

            // Marca como rejeitado e retorna falha
            $this->withdrawRepository->markAsRejected($withdraw->id, $processResult->getErrorMessage());
            
            return ProcessResultDto::failure($processResult->getErrorMessage(), [
                'withdraw_id' => $withdraw->id,
                'error_code' => 'STRATEGY_PROCESSING_FAILED'
            ]);
        }

        // PASSO 7: Debita saldo da conta (OPERAÇÃO CRÍTICA)
        $newBalance = $account->balance - $withdraw->amount;
        $balanceUpdated = $this->accountRepository->updateBalance($account->id, $newBalance);

        if (!$balanceUpdated) {
            throw new \RuntimeException('Failed to update account balance');
        }

        $this->logger->info('Account balance updated', [
            'account_id' => $account->id,
            'previous_balance' => $account->balance,
            'debited_amount' => $withdraw->amount,
            'new_balance' => $newBalance
        ]);

        // PASSO 8: Marca saque como concluído
        $completionUpdated = $this->withdrawRepository->markAsCompleted($withdraw->id);

        if (!$completionUpdated) {
            throw new \RuntimeException('Failed to mark withdrawal as completed');
        }

        // PASSO 9: Dispara evento para notificação por email (ASSÍNCRONO)
        $this->eventDispatcher->dispatch(new WithdrawProcessedEvent(
            $withdraw->id,
            $account->id,
            (float) $withdraw->amount,
            $request->pixKey
        ));

        $this->logger->info('Immediate withdrawal completed successfully', [
            'withdraw_id' => $withdraw->id,
            'account_id' => $account->id,
            'amount' => $withdraw->amount,
            'new_balance' => $newBalance,
            'pix_key' => $request->pixKey
        ]);

        // PASSO 10: Retorna resposta de sucesso
        return ProcessResultDto::success([
            'withdraw_id' => $withdraw->id,
            'status' => 'completed',
            'amount' => $withdraw->amount,
            'pix_key' => $request->pixKey,
            'previous_balance' => $account->balance,
            'new_balance' => $newBalance,
            'processed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Valida requisição de saque para processamento imediato
     */
    public function validateImmediateRequest(WithdrawRequestDto $request): ProcessResultDto
    {
        // Verifica se é realmente imediato
        if ($request->isScheduled()) {
            return ProcessResultDto::failure('Cannot process scheduled withdrawal as immediate', [
                'error_code' => 'INVALID_REQUEST_TYPE'
            ]);
        }

        // Valida valor
        if ($request->amount <= 0) {
            return ProcessResultDto::failure('Amount must be greater than zero', [
                'error_code' => 'INVALID_AMOUNT'
            ]);
        }

        // Valida chave PIX
        if (empty($request->pixKey)) {
            return ProcessResultDto::failure('PIX key is required', [
                'error_code' => 'MISSING_PIX_KEY'
            ]);
        }

        // Valida método
        if (strtolower($request->method) !== 'pix') {
            return ProcessResultDto::failure('Only PIX method is supported', [
                'error_code' => 'UNSUPPORTED_METHOD'
            ]);
        }

        return ProcessResultDto::success(['validation' => 'passed']);
    }
}