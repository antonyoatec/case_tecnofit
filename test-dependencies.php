<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;

! defined('BASE_PATH') && define('BASE_PATH', __DIR__);

require __DIR__ . '/vendor/autoload.php';

// Initialize Hyperf
Hyperf\Di\ClassLoader::init();
$container = require __DIR__ . '/config/container.php';
ApplicationContext::setContainer($container);

echo "=== Testando Dependências ===\n\n";

// Test 1: WithdrawOrchestrator
try {
    echo "1. WithdrawOrchestrator... ";
    $orch = $container->get(\App\Service\WithdrawOrchestrator::class);
    echo "✓ OK\n";
} catch (\Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 2: ImmediateWithdrawService
try {
    echo "2. ImmediateWithdrawService... ";
    $service = $container->get(\App\Service\ImmediateWithdrawService::class);
    echo "✓ OK\n";
} catch (\Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 3: WithdrawStrategyFactory
try {
    echo "3. WithdrawStrategyFactory... ";
    $factory = $container->get(\App\Strategy\WithdrawStrategyFactory::class);
    echo "✓ OK\n";
} catch (\Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 4: AccountRepository
try {
    echo "4. AccountRepository... ";
    $repo = $container->get(\App\Repository\AccountRepositoryInterface::class);
    echo "✓ OK\n";
} catch (\Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 5: AccountController
try {
    echo "5. AccountController... ";
    $controller = $container->get(\App\Controller\AccountController::class);
    echo "✓ OK\n";
} catch (\Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 6: PixWithdrawStrategy
try {
    echo "6. PixWithdrawStrategy... ";
    $strategy = $container->get(\App\Strategy\PixWithdrawStrategy::class);
    echo "✓ OK\n";
} catch (\Throwable $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Teste Completo ===\n";
