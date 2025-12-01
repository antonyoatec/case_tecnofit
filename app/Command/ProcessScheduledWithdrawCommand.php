<?php

declare(strict_types=1);

namespace App\Command;

use App\Crontab\ProcessScheduledWithdrawCron;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;

/**
 * Process Scheduled Withdraw Command - FOR TESTING/MANUAL EXECUTION
 * Allows manual execution of scheduled withdrawal processing
 */
#[Command]
class ProcessScheduledWithdrawCommand extends HyperfCommand
{
    #[Inject]
    protected ContainerInterface $container;

    public function __construct()
    {
        parent::__construct('withdraw:process-scheduled');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Manually process scheduled withdrawals');
        $this->setHelp('This command manually executes the scheduled withdrawal processing logic');
    }

    public function handle()
    {
        $this->line('Starting manual processing of scheduled withdrawals...', 'info');

        try {
            $cron = $this->container->get(ProcessScheduledWithdrawCron::class);
            $cron->execute();

            $this->line('Scheduled withdrawal processing completed successfully!', 'info');
            return 0;

        } catch (\Exception $e) {
            $this->line('Error processing scheduled withdrawals: ' . $e->getMessage(), 'error');
            $this->line('Stack trace: ' . $e->getTraceAsString(), 'error');
            return 1;
        }
    }
}