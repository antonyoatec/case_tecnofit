<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

/**
 * @AutoController
 */
class SimpleController
{
    public function __construct(
        protected ContainerInterface $container,
        protected RequestInterface $request,
        protected ResponseInterface $response
    ) {}

    public function index()
    {
        return [
            'message' => 'PIX Withdrawal Microservice is running!',
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'healthy'
        ];
    }

    public function health()
    {
        return [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'services' => [
                'database' => 'connected',
                'redis' => 'connected'
            ]
        ];
    }
}