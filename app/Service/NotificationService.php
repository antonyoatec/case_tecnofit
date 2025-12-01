<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * Notification Service - FOCUSED ON CASE REQUIREMENTS
 * Handles email notifications for withdrawal confirmations
 */
class NotificationService
{
    #[Inject]
    private LoggerInterface $logger;

    /**
     * Send withdrawal notification email
     * Requirements 5.1, 5.2: Email with date, amount, PIX key
     */
    public function sendWithdrawNotification(
        string $withdrawId,
        string $accountId,
        float $amount,
        string $pixKey
    ): void {
        $this->logger->info('Sending withdrawal notification email', [
            'withdraw_id' => $withdrawId,
            'account_id' => $accountId,
            'amount' => $amount,
            'pix_key' => $pixKey
        ]);

        // Email content as specified in requirements
        $subject = 'PIX Withdrawal Confirmation';
        $body = $this->buildEmailBody($withdrawId, $amount, $pixKey);

        // Log email notification (in production, this would send actual email)
        $this->logger->info('Withdrawal notification email would be sent', [
            'withdraw_id' => $withdrawId,
            'recipient' => $pixKey,
            'subject' => $subject,
            'body' => $body
        ]);
    }

    /**
     * Build email body with required information
     * Requirements 5.2: Include date, amount, PIX key
     */
    private function buildEmailBody(string $withdrawId, float $amount, string $pixKey): string
    {
        $date = date('d/m/Y H:i:s');
        $formattedAmount = 'R$ ' . number_format($amount, 2, ',', '.');

        return "
Confirmação de Saque PIX

Seu saque foi processado com sucesso!

Detalhes da transação:
- Data: {$date}
- Valor: {$formattedAmount}
- Chave PIX: {$pixKey}
- ID da transação: {$withdrawId}

Este é um email automático, não responda.

PIX Withdrawal Service
        ";
    }
}