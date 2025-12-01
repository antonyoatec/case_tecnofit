<?php

declare(strict_types=1);

namespace App\Crontab;

use App\Service\WithdrawService;
use App\Repository\AccountWithdrawRepositoryInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * Cron de Processamento de Saques Agendados - FOCADO NOS REQUISITOS DO CASE
 * Executa a cada minuto para processar saques PIX agendados
 */
#[Crontab(rule: "* * * * *", name: "ProcessScheduledWithdraw")]
class ProcessScheduledWithdrawCron
{
    #[Inject]
    private WithdrawService $withdrawService;

    #[Inject]
    private AccountWithdrawRepositoryInterface $withdrawRepository;

    #[Inject]
    private LoggerInterface $logger;

    /**
     * Executa cron job - FUNCIONALIDADE PRINCIPAL DO CASE
     * Processa saques agendados que estão prontos
     */
    public function execute(): void
    {
        $this->logger->info('Starting scheduled withdrawal processing cron job');

        try {
            // Obtém saques agendados pendentes (operação atômica para escalabilidade horizontal)
            $withdrawals = $this->withdrawRepository->findPendingScheduled(50);

            if ($withdrawals->isEmpty()) {
                $this->logger->info('No scheduled withdrawals to process');
                return;
            }

            $this->logger->info('Found scheduled withdrawals to process', [
                'count' => $withdrawals->count()
            ]);

            $processed = 0;
            $successful = 0;
            $failed = 0;

            foreach ($withdrawals as $withdrawal) {
                try {
                    $this->logger->info('Processing scheduled withdrawal', [
                        'withdraw_id' => $withdrawal->id,
                        'account_id' => $withdrawal->account_id,
                        'amount' => $withdrawal->amount,
                        'scheduled_for' => $withdrawal->scheduled_for->format('Y-m-d H:i:s')
                    ]);

                    // Processa usando serviço existente (gerencia toda lógica de negócio)
                    $result = $this->withdrawService->processScheduledWithdraw($withdrawal);

                    if ($result->isSuccess()) {
                        $successful++;
                        $this->logger->info('Scheduled withdrawal processed successfully', [
                            'withdraw_id' => $withdrawal->id
                        ]);
                    } else {
                        $failed++;
                        $this->logger->warning('Scheduled withdrawal processing failed', [
                            'withdraw_id' => $withdrawal->id,
                            'error' => $result->getErrorMessage(),
                            'error_code' => $result->getMetadata('error_code', 'UNKNOWN')
                        ]);
                    }

                    $processed++;

                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->error('Exception during scheduled withdrawal processing', [
                        'withdraw_id' => $withdrawal->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Marca como rejeitado para prevenir reprocessamento
                    try {
                        $this->withdrawRepository->markAsRejected(
                            $withdrawal->id, 
                            'Cron processing error: ' . $e->getMessage()
                        );
                    } catch (\Exception $markError) {
                        $this->logger->error('Failed to mark withdrawal as rejected', [
                            'withdraw_id' => $withdrawal->id,
                            'mark_error' => $markError->getMessage()
                        ]);
                    }
                }
            }

            $this->logger->info('Scheduled withdrawal processing completed', [
                'total_found' => $withdrawals->count(),
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Critical error in scheduled withdrawal cron job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}