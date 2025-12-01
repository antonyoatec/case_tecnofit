<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Hyperf\Di\Annotation\Inject;

#[AutoController]
class HealthController extends AbstractController
{
    #[Inject]
    protected Redis $redis;

    public function check()
    {
        $status = 'healthy';
        $checks = [];

        // Database health check
        try {
            Db::select('SELECT 1');
            $checks['database'] = 'healthy';
        } catch (\Exception $e) {
            $checks['database'] = 'unhealthy';
            $status = 'unhealthy';
        }

        // Redis health check
        try {
            $this->redis->ping();
            $checks['redis'] = 'healthy';
        } catch (\Exception $e) {
            $checks['redis'] = 'unhealthy';
            $status = 'unhealthy';
        }

        $response = [
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'service' => 'PIX Withdrawal Microservice',
            'checks' => $checks,
        ];

        return $this->response->json($response)
            ->withStatus($status === 'healthy' ? 200 : 503);
    }
}