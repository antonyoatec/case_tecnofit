<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Validation;

use App\Validation\PixKeyValidator;
use PHPUnit\Framework\TestCase;

/**
 * PIX Key Validator Test - FOCUSED ON CASE REQUIREMENTS
 * Only tests EMAIL validation as specified in the case
 */
class PixKeyValidatorTest extends TestCase
{
    private PixKeyValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PixKeyValidator();
    }

    public function testValidEmail(): void
    {
        $result = $this->validator->validate('user@example.com');
        $this->assertTrue($result->isValid());
    }

    public function testValidComplexEmail(): void
    {
        $result = $this->validator->validate('user.name+tag@example.co.uk');
        $this->assertTrue($result->isValid());
    }

    public function testInvalidEmail(): void
    {
        $result = $this->validator->validate('invalid-email');
        $this->assertTrue($result->isInvalid());
        $this->assertStringContainsString('Invalid email format', $result->getFirstError());
    }

    public function testEmailTooLong(): void
    {
        $longEmail = str_repeat('a', 250) . '@example.com';
        $result = $this->validator->validate($longEmail);
        $this->assertTrue($result->isInvalid());
        $this->assertStringContainsString('Email is too long', $result->getFirstError());
    }

    public function testEmptyEmail(): void
    {
        $result = $this->validator->validate('');
        $this->assertTrue($result->isInvalid());
        $this->assertStringContainsString('PIX email cannot be empty', $result->getFirstError());
    }

    public function testEmailWithSpaces(): void
    {
        $result = $this->validator->validate('  user@example.com  ');
        $this->assertTrue($result->isValid()); // Should trim spaces
    }
}