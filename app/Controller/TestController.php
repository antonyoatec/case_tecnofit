<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\NotificationService;
use App\Event\WithdrawProcessedEvent;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\Di\Annotation\Inject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Test Controller - FOR DEVELOPMENT/TESTING ONLY
 * Provides endpoints to test email functionality
 */
#[AutoController(prefix: "/test")]
class TestController extends AbstractController
{
    #[Inject]
    private NotificationService $notificationService;

    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    #[Inject]
    private LoggerInterface $logger;

    /**
     * Test withdrawal notification event - ESSENTIAL FOR CASE TESTING
     * GET /test/withdrawal-event?email=user@example.com
     */

    /**
     * Test withdrawal notification event
     * GET /test/withdrawal-event?email=user@example.com
     */
    #[GetMapping(path: "/withdrawal-event")]
    public function testWithdrawalEvent()
    {
        $email = $this->request->input('email');
        
        if (empty($email)) {
            return $this->response->json([
                'success' => false,
                'error' => 'email parameter is required'
            ])->withStatus(400);
        }

        $this->logger->info('Testing withdrawal event dispatch', [
            'email' => $email
        ]);

        // Dispatch test event
        $event = new WithdrawProcessedEvent(
            'test-withdraw-' . uniqid(),
            'test-account-' . uniqid(),
            100.50,
            $email
        );

        $this->eventDispatcher->dispatch($event);

        return $this->response->json([
            'success' => true,
            'message' => 'Withdrawal event dispatched successfully',
            'event_data' => [
                'withdraw_id' => $event->withdrawId,
                'account_id' => $event->accountId,
                'amount' => $event->amount,
                'pix_key' => $event->pixKey
            ]
        ]);
    }

    /**
     * Check Mailhog status
     * GET /test/mailhog
     */
    #[GetMapping(path: "/mailhog")]
    public function checkMailhog()
    {
        $mailhogHost = config('mail.mailers.smtp.host', 'mailhog');
        $mailhogPort = config('mail.mailers.smtp.port', 1025);

        try {
            $connection = @fsockopen($mailhogHost, $mailhogPort, $errno, $errstr, 5);
            
            if ($connection) {
                fclose($connection);
                $status = 'connected';
                $message = "Successfully connected to Mailhog at {$mailhogHost}:{$mailhogPort}";
            } else {
                $status = 'failed';
                $message = "Failed to connect to Mailhog: {$errstr} ({$errno})";
            }

        } catch (\Exception $e) {
            $status = 'error';
            $message = "Error checking Mailhog: " . $e->getMessage();
        }

        return $this->response->json([
            'success' => $status === 'connected',
            'status' => $status,
            'message' => $message,
            'config' => [
                'host' => $mailhogHost,
                'port' => $mailhogPort,
                'web_ui' => "http://{$mailhogHost}:8025"
            ]
        ]);
    }
}