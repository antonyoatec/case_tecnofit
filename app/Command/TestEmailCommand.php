<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\NotificationService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;

/**
 * Test Email Command - FOR CASE TESTING ONLY
 * Tests email integration with Mailhog
 */
#[Command]
class TestEmailCommand extends HyperfCommand
{
    #[Inject]
    private NotificationService $notificationService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct('test:email');
        $this->setDescription('Test email sending to Mailhog');
    }

    public function configure()
    {
        parent::configure();
        $this->addArgument('email', null, 'Email address to send test to', 'test@example.com');
    }

    public function handle()
    {
        $email = $this->input->getArgument('email');
        
        $this->line("Testing email sending to: {$email}");
        $this->line("Make sure Mailhog is running on http://localhost:8025");

        try {
            $this->notificationService->sendWithdrawNotification(
                'test-withdraw-' . uniqid(),
                'test-account-' . uniqid(),
                150.75,
                $email
            );

            $this->info("✅ Test email sent successfully!");
            $this->line("Check Mailhog web interface: http://localhost:8025");

        } catch (\Exception $e) {
            $this->error("❌ Failed to send test email: " . $e->getMessage());
            $this->line("Make sure:");
            $this->line("1. Docker containers are running: docker-compose up -d");
            $this->line("2. Mailhog is accessible: curl http://localhost:8025");
            return 1;
        }

        return 0;
    }
}