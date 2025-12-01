<?php

declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

// Health check
Router::get('/health', function () {
    return ['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')];
});

// Account routes
Router::get('/account/{id}/balance', 'App\Controller\AccountController@getBalance');

// Withdraw endpoint - calling controller
Router::post('/account/{id}/balance/withdraw', function ($id) {
    $container = \Hyperf\Context\ApplicationContext::getContainer();
    $response = $container->get(\Hyperf\HttpServer\Contract\ResponseInterface::class);
    
    try {
        $logger = $container->get(\Psr\Log\LoggerInterface::class);
        $logger->info('Route called', ['account_id' => $id]);
        
        $controller = $container->get(\App\Controller\AccountController::class);
        $result = $controller->withdraw($id);
        
        $logger->info('Controller returned', ['result' => $result]);
        
        return $result;
    } catch (\Throwable $e) {
        $logger = $container->get(\Psr\Log\LoggerInterface::class);
        $logger->error('Exception in route', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        return $response->json([
            'success' => false,
            'error' => [
                'code' => 'EXCEPTION',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10)
            ]
        ])->withStatus(500);
    }
});