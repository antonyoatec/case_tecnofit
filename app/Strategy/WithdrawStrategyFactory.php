<?php

declare(strict_types=1);

namespace App\Strategy;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerInterface;

/**
 * Withdrawal Strategy Factory - FOCUSED ON CASE REQUIREMENTS
 * Resolves the appropriate withdrawal strategy based on method
 */
class WithdrawStrategyFactory
{
    private array $strategies = [];

    public function __construct(
        private ContainerInterface $container
    ) {
        // Register available strategies
        $this->registerStrategy(PixWithdrawStrategy::class);
    }

    /**
     * Get strategy for withdrawal method
     */
    public function getStrategy(string $method): WithdrawMethodInterface
    {
        foreach ($this->strategies as $strategyClass) {
            $strategy = $this->container->get($strategyClass);
            
            if ($strategy->supports($method)) {
                return $strategy;
            }
        }

        throw new \InvalidArgumentException("Unsupported withdrawal method: {$method}");
    }

    /**
     * Register a withdrawal strategy
     */
    private function registerStrategy(string $strategyClass): void
    {
        $this->strategies[] = $strategyClass;
    }

    /**
     * Get all available withdrawal methods
     */
    public function getAvailableMethods(): array
    {
        $methods = [];
        
        foreach ($this->strategies as $strategyClass) {
            $strategy = $this->container->get($strategyClass);
            
            // For PIX strategy, we know it supports 'pix'
            if ($strategy instanceof PixWithdrawStrategy) {
                $methods[] = 'pix';
            }
        }

        return $methods;
    }
}