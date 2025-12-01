<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Strategy;

use App\Strategy\PixWithdrawStrategy;
use App\Dto\WithdrawRequestDto;
use App\Dto\ValidationResultDto;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Validation\PixKeyValidator;
use App\Repository\AccountWithdrawPixRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class PixWithdrawStrategyTest extends TestCase
{
    private PixWithdrawStrategy $strategy;
    private $pixKeyValidator;
    private $pixRepository;

    protected function setUp(): void
    {
        $this->pixKeyValidator = Mockery::mock(PixKeyValidator::class);
        $this->pixRepository = Mockery::mock(AccountWithdrawPixRepositoryInterface::class);
        
        $this->strategy = new PixWithdrawStrategy();
        
        // Use reflection to inject mocked dependencies
        $reflection = new \ReflectionClass($this->strategy);
        
        $validatorProperty = $reflection->getProperty('pixKeyValidator');
        $validatorProperty->setAccessible(true);
        $validatorProperty->setValue($this->strategy, $this->pixKeyValidator);
        
        $repositoryProperty = $reflection->getProperty('pixRepository');
        $repositoryProperty->setAccessible(true);
        $repositoryProperty->setValue($this->strategy, $this->pixRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testSupportsPix(): void
    {
        $this->assertTrue($this->strategy->supports('pix'));
        $this->assertTrue($this->strategy->supports('PIX'));
        $this->assertFalse($this->strategy->supports('ted'));
        $this->assertFalse($this->strategy->supports('boleto'));
    }

    public function testValidateWithValidRequest(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'user@example.com'
        );

        $this->pixKeyValidator
            ->shouldReceive('validate')
            ->with('user@example.com')
            ->once()
            ->andReturn(ValidationResultDto::valid());

        $result = $this->strategy->validate($request);
        
        $this->assertTrue($result->isValid());
    }

    public function testValidateWithInvalidAmount(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: -10.00,
            pixKey: 'user@example.com'
        );

        $result = $this->strategy->validate($request);
        
        $this->assertTrue($result->isInvalid());
        $this->assertStringContainsString('Amount must be greater than zero', $result->getFirstError());
    }

    public function testValidateWithZeroAmount(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 0.00,
            pixKey: 'user@example.com'
        );

        $result = $this->strategy->validate($request);
        
        $this->assertTrue($result->isInvalid());
        $this->assertStringContainsString('Amount must be greater than zero', $result->getFirstError());
    }

    public function testValidateWithInvalidPixKey(): void
    {
        $request = new WithdrawRequestDto(
            method: 'pix',
            amount: 100.00,
            pixKey: 'invalid-email'
        );

        $this->pixKeyValidator
            ->shouldReceive('validate')
            ->with('invalid-email')
            ->once()
            ->andReturn(ValidationResultDto::invalid([
                ['field' => 'pix_key', 'message' => 'Invalid email format', 'code' => 'INVALID_EMAIL']
            ]));

        $result = $this->strategy->validate($request);
        
        $this->assertTrue($result->isInvalid());
        $this->assertStringContainsString('Invalid email format', $result->getFirstError());
    }

    public function testGetRequiredFields(): void
    {
        $fields = $this->strategy->getRequiredFields();
        
        $this->assertCount(3, $fields);
        $this->assertContains('method', $fields);
        $this->assertContains('amount', $fields);
        $this->assertContains('pix_key', $fields);
    }
}