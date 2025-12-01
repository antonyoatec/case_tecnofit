<?php

declare(strict_types=1);

namespace App\Listener;

use App\Event\WithdrawProcessedEvent;
use App\Service\NotificationService;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * Withdraw Processed Listener - FOCUSED ON CASE REQUIREMENTS
 * Handles email notifications when withdrawals are processed
 */
#[Listener]
class WithdrawProcessedListener implements ListenerInterface
{
    #[Inject]
    private NotificationService $notificationService;

    #[Inject]
    private LoggerInterface $logger;

    public function listen(): array
    {
        return [
            WithdrawProcessedEvent::class,
        ];
    }

    public function process(object $event): void
    {
        if (!$event instanceof WithdrawProcessedEvent) {
            return;
        }

        $this->logger->info('Processing withdrawal notification', [
            'withdraw_id' => $event->withdrawId,
            'account_id' => $event->accountId,
            'amount' => $event->amount
        ]);

        try {
            // Send email notification asynchronously
            $this->notificationService->sendWithdrawNotification(
                $event->withdrawId,
                $event->accountId,
                $event->amount,
                $event->pixKey
            );

            $this->logger->info('Withdrawal notification sent successfully', [
                'withdraw_id' => $event->withdrawId
            ]);

        } catch (\Exception $e) {
            // Email failure should NOT affect withdrawal success
            $this->logger->error('Failed to send withdrawal notification', [
                'withdraw_id' => $event->withdrawId,
                'error' => $e->getMessage()
            ]);
        }
    }
}