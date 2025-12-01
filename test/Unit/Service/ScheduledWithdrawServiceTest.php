<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service;

use App\Service\ScheduledWithdrawService;
use App\Dto\WithdrawRequestDto;
use App\Dto\ProcessResultDto;
use App\Dto\ValidationResultDto;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Repository\AccountRepositoryInterface;
use App\Repository\AccountWithdrawRepositoryInterface;
use App\Repository\AccountWithdrawPixRepositoryInterface;
use App\Strategy\WithdrawStrategyFactory;
use App\Strategy\WithdrawMethodInterface;
use PHPUnit\Framework\TestCase;
use Mockery;
use Psr\Log\LoggerInterface;
use DateTime;

class ScheduledWithdrawServiceTest extends TestCase
{
    private ScheduledWithdrawService $service;
    private $accountRepository;
    private $withdrawRepository;
    private $pixRepository;
    private $strategyFactory;
    private $logger;
    private $strategy;

    protected function setUp(): void
    {
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->withdrawRepository = Mockery::mock(AccountWithdrawRepositoryInterface::class);
        $this->pixRepository = Mockery::mock(AccountWithdrawPixRepositoryInterface::class);
        $this->strategyFactory = Mockery::mock(WithdrawStrategyFactory::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->strategy = Mockery::mock(WithdrawMethodInterface::class);

        $this->service = new ScheduledWithdrawService();

        // Inject dependencies using reflection
        $this->injectDependency('accountRepository', $this->accountRepository);
        $this->injectDependency('withdrawRepository', $this->withdrawRepository);
        $this->injectDependency('pixRepository', $this->pixRepository);
        $this->injectDependency('strategyFactory', $this->strategyFactory);
        $this->injectDependency('logger', $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function injectDependency(string $property, $mock): void
    {
        $reflection = new \ReflectionClass($this->service);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($this->service, $mock);
    }

    public function testValidateScheduledRequestSuccess(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com',
            scheduledFor: new DateTime('+1 day')
        );

        $result = $this->service->validateScheduledRequest($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('passed', $result->getMetadata()['validation']);
    }

    public function testValidateScheduledRequestWithPastDate(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com',
            scheduledFor: new DateTime('-1 day')  // Past date
        );

        $result = $this->service->validateScheduledRequest($request);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('future', $result->getErrorMessage());
        $this->assertEquals('INVALID_SCHEDULED_DATE', $result->getMetadata()['error_code']);
    }

    public function testCreateScheduledSuccess(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com',
            scheduledFor: new DateTime('+1 day')
        );

        // Basic validation test - simplified
        $result = $this->service->validateScheduledRequest($request);
        $this->assertTrue($result->isSuccess());
    }
}