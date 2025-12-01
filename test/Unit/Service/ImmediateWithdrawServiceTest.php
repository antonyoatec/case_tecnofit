<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service;

use App\Service\ImmediateWithdrawService;
use App\Dto\WithdrawRequestDto;
use App\Dto\ProcessResultDto;
use App\Dto\ValidationResultDto;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Repository\AccountRepositoryInterface;
use App\Repository\AccountWithdrawRepositoryInterface;
use App\Repository\AccountWithdrawPixRepositoryInterface;
use App\Strategy\WithdrawStrategyFactory;
use App\Strategy\WithdrawMethodInterface;
use App\Exception\InsufficientBalanceException;
use App\Exception\AccountNotFoundException;
use PHPUnit\Framework\TestCase;
use Mockery;
use Psr\Log\LoggerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ImmediateWithdrawServiceTest extends TestCase
{
    private ImmediateWithdrawService $service;
    private $accountRepository;
    private $withdrawRepository;
    private $pixRepository;
    private $strategyFactory;
    private $logger;
    private $eventDispatcher;
    private $strategy;

    protected function setUp(): void
    {
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->withdrawRepository = Mockery::mock(AccountWithdrawRepositoryInterface::class);
        $this->pixRepository = Mockery::mock(AccountWithdrawPixRepositoryInterface::class);
        $this->strategyFactory = Mockery::mock(WithdrawStrategyFactory::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $this->strategy = Mockery::mock(WithdrawMethodInterface::class);

        $this->service = new ImmediateWithdrawService();

        // Inject dependencies using reflection
        $this->injectDependency('accountRepository', $this->accountRepository);
        $this->injectDependency('withdrawRepository', $this->withdrawRepository);
        $this->injectDependency('pixRepository', $this->pixRepository);
        $this->injectDependency('strategyFactory', $this->strategyFactory);
        $this->injectDependency('logger', $this->logger);
        $this->injectDependency('eventDispatcher', $this->eventDispatcher);
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

    public function testProcessImmediateWithScheduledRequest(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com',
            scheduledFor: new \DateTime('+1 day')  // Scheduled request
        );

        $this->logger->shouldReceive('info')->once();

        $result = $this->service->processImmediate('account-123', $request);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('immediate withdrawals', $result->getErrorMessage());
        $this->assertEquals('INVALID_REQUEST_TYPE', $result->getMetadata()['error_code']);
    }

    public function testProcessImmediateWithValidationFailure(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: -10.00,  // Invalid amount
            pixKey: 'user@example.com'
        );

        $this->logger->shouldReceive('info')->once();
        $this->logger->shouldReceive('warning')->once();

        // Mock strategy factory and validation
        $this->strategyFactory
            ->shouldReceive('getStrategy')
            ->with('pix')
            ->once()
            ->andReturn($this->strategy);

        $this->strategy
            ->shouldReceive('validate')
            ->with($request)
            ->once()
            ->andReturn(ValidationResultDto::invalid([
                ['field' => 'amount', 'message' => 'Amount must be greater than zero', 'code' => 'INVALID_AMOUNT']
            ]));

        $result = $this->service->processImmediate('account-123', $request);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('Validation failed', $result->getErrorMessage());
    }

    public function testProcessImmediateWithAccountNotFound(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com'
        );

        $this->logger->shouldReceive('info')->once();
        $this->logger->shouldReceive('error')->once();

        // Mock strategy validation (success)
        $this->strategyFactory
            ->shouldReceive('getStrategy')
            ->with('pix')
            ->once()
            ->andReturn($this->strategy);

        $this->strategy
            ->shouldReceive('validate')
            ->with($request)
            ->once()
            ->andReturn(ValidationResultDto::valid());

        // Mock account not found
        $this->accountRepository
            ->shouldReceive('findByIdForUpdate')
            ->with('nonexistent')
            ->once()
            ->andReturn(null);

        $result = $this->service->processImmediate('nonexistent', $request);

        $this->assertTrue($result->isFailure());
        $this->assertEquals('Account not found', $result->getErrorMessage());
        $this->assertEquals('ACCOUNT_NOT_FOUND', $result->getMetadata()['error_code']);
    }

    public function testProcessImmediateWithInsufficientBalance(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com'
        );

        $account = new Account([
            'id' => 'account-123',
            'name' => 'Test User',
            'balance' => 50.00  // Insufficient balance
        ]);

        $this->logger->shouldReceive('info')->twice();
        $this->logger->shouldReceive('warning')->once();

        // Mock strategy validation (success)
        $this->strategyFactory
            ->shouldReceive('getStrategy')
            ->with('pix')
            ->once()
            ->andReturn($this->strategy);

        $this->strategy
            ->shouldReceive('validate')
            ->with($request)
            ->once()
            ->andReturn(ValidationResultDto::valid());

        // Mock account with insufficient balance
        $this->accountRepository
            ->shouldReceive('findByIdForUpdate')
            ->with('account-123')
            ->once()
            ->andReturn($account);

        $result = $this->service->processImmediate('account-123', $request);

        $this->assertTrue($result->isFailure());
        $this->assertEquals('saldo insuficiente', $result->getErrorMessage());
        $this->assertEquals('INSUFFICIENT_BALANCE', $result->getMetadata()['error_code']);
    }

    public function testProcessImmediateSuccess(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com'
        );

        $account = new Account([
            'id' => 'account-123',
            'name' => 'Test User',
            'balance' => 200.00  // Sufficient balance
        ]);

        $withdraw = new AccountWithdraw([
            'id' => 'withdraw-123',
            'account_id' => 'account-123',
            'method' => 'pix',
            'amount' => 100.00,
            'status' => AccountWithdraw::STATUS_PENDING
        ]);

        $pixDetails = new AccountWithdrawPix([
            'id' => 'pix-123',
            'account_withdraw_id' => 'withdraw-123',
            'type' => AccountWithdrawPix::TYPE_EMAIL,
            'key' => 'user@example.com'
        ]);

        // Mock all logger calls
        $this->logger->shouldReceive('info')->times(6);

        // Mock strategy validation and processing
        $this->strategyFactory
            ->shouldReceive('getStrategy')
            ->with('pix')
            ->once()
            ->andReturn($this->strategy);

        $this->strategy
            ->shouldReceive('validate')
            ->with($request)
            ->once()
            ->andReturn(ValidationResultDto::valid());

        $this->strategy
            ->shouldReceive('process')
            ->with($account, $withdraw)
            ->once()
            ->andReturn(ProcessResultDto::success(['method' => 'pix']));

        // Mock account repository
        $this->accountRepository
            ->shouldReceive('findByIdForUpdate')
            ->with('account-123')
            ->once()
            ->andReturn($account);

        $this->accountRepository
            ->shouldReceive('updateBalance')
            ->with('account-123', 100.00)  // New balance: 200 - 100
            ->once()
            ->andReturn(true);

        // Mock withdraw repository
        $this->withdrawRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($withdraw);

        $this->withdrawRepository
            ->shouldReceive('updateStatusAtomically')
            ->with('withdraw-123', AccountWithdraw::STATUS_PENDING, AccountWithdraw::STATUS_PROCESSING)
            ->once()
            ->andReturn(true);

        $this->withdrawRepository
            ->shouldReceive('markAsCompleted')
            ->with('withdraw-123')
            ->once()
            ->andReturn(true);

        // Mock PIX repository
        $this->pixRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($pixDetails);

        // Mock event dispatcher
        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once();

        $result = $this->service->processImmediate('account-123', $request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('completed', $result->getMetadata()['status']);
        $this->assertEquals(100.00, $result->getMetadata()['amount']);
        $this->assertEquals(100.00, $result->getMetadata()['new_balance']);
        $this->assertEquals('user@example.com', $result->getMetadata()['pix_key']);
    }

    public function testValidateImmediateRequestWithScheduled(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com',
            scheduledFor: new \DateTime('+1 day')
        );

        $result = $this->service->validateImmediateRequest($request);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('scheduled withdrawal', $result->getErrorMessage());
    }

    public function testValidateImmediateRequestWithInvalidAmount(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: -10.00,
            pixKey: 'user@example.com'
        );

        $result = $this->service->validateImmediateRequest($request);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('greater than zero', $result->getErrorMessage());
    }

    public function testValidateImmediateRequestWithEmptyPixKey(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: ''
        );

        $result = $this->service->validateImmediateRequest($request);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('PIX key is required', $result->getErrorMessage());
    }

    public function testValidateImmediateRequestWithUnsupportedMethod(): void
    {
        $request = new WithdrawRequestDto(
            method: 'ted',
            amount: 100.00,
            pixKey: 'user@example.com'
        );

        $result = $this->service->validateImmediateRequest($request);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('Only PIX method', $result->getErrorMessage());
    }

    public function testValidateImmediateRequestSuccess(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com'
        );

        $result = $this->service->validateImmediateRequest($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('passed', $result->getMetadata()['validation']);
    }
}