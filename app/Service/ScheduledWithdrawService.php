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
use App\Exception\AccountNotFoundException;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Component;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use DateTime;

/**
 * Serviço de Saque Agendado - FOCADO NOS REQUISITOS DO CASE
 * Gerencia criação e validação de saques PIX agendados
 */
class ScheduledWithdrawService
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

    /**
     * Cria saque agendado - FUNCIONALIDADE PRINCIPAL
     * Apenas cria o registro, processamento acontece via cron job
     */
    public function createScheduled(string $accountId, WithdrawRequestDto $request): ProcessResultDto
    {
        $this->logger->info('Creating scheduled withdrawal', [
            'account_id' => $accountId,
            'amount' => $request->amount,
            'pix_key' => $request->pixKey,
            'scheduled_for' => $request->scheduledFor?->format('Y-m-d H:i:s')
        ]);

        // Valida que é realmente agendado
        if (!$request->isScheduled()) {
            return ProcessResultDto::failure('This service only handles scheduled withdrawals', 'INVALID_REQUEST_TYPE');
        }

        try {
            // Obtém e valida a estratégia
            $strategy = $this->strategyFactory->getStrategy($request->method);
            
            // Valida requisição usando a estratégia
            $validationResult = $strategy->validate($request);
            if ($validationResult->isInvalid()) {
                $this->logger->warning('Scheduled withdrawal validation failed', [
                    'account_id' => $accountId,
                    'validation_errors' => $validationResult->getErrors()
                ]);

                return ProcessResultDto::failure(
                    'Validation failed: ' . $validationResult->getFirstError(),
                    'VALIDATION_ERROR',
                    ['validation_errors' => $validationResult->getErrors()]
                );
            }

            // Cria saque agendado dentro de transação
            return Db::transaction(function () use ($accountId, $request) {
                return $this->executeScheduledCreation($accountId, $request);
            });

        } catch (AccountNotFoundException $e) {
            $this->logger->error('Account not found for scheduled withdrawal', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return ProcessResultDto::failure('Account not found', 'ACCOUNT_NOT_FOUND');

        } catch (\Exception $e) {
            $this->logger->error('Scheduled withdrawal creation failed', [
                'account_id' => $accountId,
                'amount' => $request->amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ProcessResultDto::failure('Internal server error', 'INTERNAL_ERROR');
        }
    }

    /**
     * Executa criação de saque agendado dentro de transação
     */
    private function executeScheduledCreation(string $accountId, WithdrawRequestDto $request): ProcessResultDto
    {
        // PASSO 1: Verifica se conta existe (sem bloqueio necessário para criação)
        $account = $this->accountRepository->findById($accountId);
        
        if (!$account) {
            throw new AccountNotFoundException("Account {$accountId} not found");
        }

        $this->logger->info('Account found for scheduled withdrawal', [
            'account_id' => $accountId,
            'account_name' => $account->name,
            'current_balance' => $account->balance
        ]);

        // PASSO 2: Cria registro de saque com status PENDING
        $withdraw = new AccountWithdraw([
            'account_id' => $accountId,
            'method' => $request->method,
            'amount' => $request->amount,
            'scheduled' => true,  // Sempre true para agendado
            'scheduled_for' => $request->scheduledFor,
            'status' => AccountWithdraw::STATUS_PENDING,
        ]);

        $withdraw = $this->withdrawRepository->create($withdraw);

        $this->logger->info('Scheduled withdrawal record created', [
            'withdraw_id' => $withdraw->id,
            'scheduled_for' => $withdraw->scheduled_for->format('Y-m-d H:i:s'),
            'status' => $withdraw->status
        ]);

        // PASSO 3: Cria detalhes do PIX
        $pixDetails = new AccountWithdrawPix([
            'account_withdraw_id' => $withdraw->id,
            'type' => AccountWithdrawPix::TYPE_EMAIL,
            'pix_key' => $request->pixKey,
        ]);

        $this->pixRepository->create($pixDetails);

        $this->logger->info('PIX details created for scheduled withdrawal', [
            'withdraw_id' => $withdraw->id,
            'pix_key' => $request->pixKey,
            'pix_type' => AccountWithdrawPix::TYPE_EMAIL
        ]);

        // PASSO 4: Retorna resposta de sucesso
        return ProcessResultDto::success([
            'withdraw_id' => $withdraw->id,
            'status' => 'scheduled',
            'amount' => $withdraw->amount,
            'pix_key' => $request->pixKey,
            'scheduled_for' => $withdraw->scheduled_for->format('Y-m-d H:i:s'),
            'created_at' => $withdraw->created_at->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Valida requisição de saque agendado
     */
    public function validateScheduledRequest(WithdrawRequestDto $request): ProcessResultDto
    {
        // Verifica se é realmente agendado
        if (!$request->isScheduled()) {
            return ProcessResultDto::failure('Cannot process immediate withdrawal as scheduled', [
                'error_code' => 'INVALID_REQUEST_TYPE'
            ]);
        }

        // Validate amount
        if ($request->amount <= 0) {
            return ProcessResultDto::failure('Amount must be greater than zero', [
                'error_code' => 'INVALID_AMOUNT'
            ]);
        }

        // Validate PIX key
        if (empty($request->pixKey)) {
            return ProcessResultDto::failure('PIX key is required', [
                'error_code' => 'MISSING_PIX_KEY'
            ]);
        }

        // Validate method
        if (strtolower($request->method) !== 'pix') {
            return ProcessResultDto::failure('Only PIX method is supported', [
                'error_code' => 'UNSUPPORTED_METHOD'
            ]);
        }

        // Valida se data agendada está no futuro
        if ($request->scheduledFor <= new DateTime()) {
            return ProcessResultDto::failure('Scheduled date must be in the future', [
                'error_code' => 'INVALID_SCHEDULED_DATE'
            ]);
        }

        return ProcessResultDto::success(['validation' => 'passed']);
    }

    /**
     * Obtém saques agendados pendentes para processamento
     * Usado pelo cron job para encontrar saques prontos para processar
     */
    public function getPendingScheduled(int $limit = 50): array
    {
        $this->logger->info('Fetching pending scheduled withdrawals', [
            'limit' => $limit
        ]);

        $withdrawals = $this->withdrawRepository->findPendingScheduled($limit);

        $this->logger->info('Found pending scheduled withdrawals', [
            'count' => $withdrawals->count(),
            'limit' => $limit
        ]);

        return $withdrawals->toArray();
    }
}