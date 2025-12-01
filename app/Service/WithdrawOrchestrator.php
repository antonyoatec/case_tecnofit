<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\WithdrawRequestDto;
use App\Dto\ProcessResultDto;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Context\ApplicationContext;
use Psr\Log\LoggerInterface;

/**
 * Orquestrador de Saques - FOCADO NOS REQUISITOS DO CASE
 * Roteia requisições de saque para o serviço apropriado (imediato vs agendado)
 */
class WithdrawOrchestrator
{
    #[Inject]
    private ImmediateWithdrawService $immediateService;

    #[Inject]
    private ScheduledWithdrawService $scheduledService;

    #[Inject]
    private LoggerInterface $logger;

    /**
     * Processa requisição de saque - roteia para o serviço apropriado
     */
    public function processWithdraw(string $accountId, WithdrawRequestDto $request): ProcessResultDto
    {
        $this->logger->info('Orchestrating withdrawal request', [
            'account_id' => $accountId,
            'amount' => $request->amount,
            'method' => $request->method,
            'is_scheduled' => $request->isScheduled()
        ]);

        // Roteia para o serviço apropriado baseado no agendamento
        if ($request->isScheduled()) {
            $this->logger->info('Routing to scheduled withdrawal service');
            return $this->scheduledService->createScheduled($accountId, $request);
        } else {
            $this->logger->info('Routing to immediate withdrawal service');
            return $this->immediateService->processImmediate($accountId, $request);
        }
    }

    /**
     * Valida requisição de saque antes do processamento
     */
    public function validateRequest(WithdrawRequestDto $request): ProcessResultDto
    {
        // Validação básica
        if (empty($request->method)) {
            return ProcessResultDto::failure('Method is required', 'MISSING_METHOD');
        }

        if ($request->amount <= 0) {
            return ProcessResultDto::failure('Amount must be greater than zero', 'INVALID_AMOUNT');
        }

        if (empty($request->pixKey)) {
            return ProcessResultDto::failure('PIX key is required', 'MISSING_PIX_KEY');
        }

        // Valida data agendada se fornecida
        if ($request->isScheduled() && $request->scheduledFor <= new \DateTime()) {
            return ProcessResultDto::failure('Scheduled date must be in the future', 'INVALID_SCHEDULED_DATE');
        }

        return ProcessResultDto::success(['validation' => 'passed']);
    }
}