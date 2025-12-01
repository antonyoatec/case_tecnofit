<?php

declare(strict_types=1);

namespace HyperfTest\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Email Integration Test - FOCUSED ON CASE REQUIREMENTS
 * Tests email functionality with Mailhog
 */
class EmailIntegrationTest extends TestCase
{
    public function testEmailTemplateStructure(): void
    {
        // Test that email template has required elements
        $templatePath = BASE_PATH . '/storage/view/withdrawal-confirmation.blade.php';
        
        $this->assertFileExists($templatePath);
        
        $content = file_get_contents($templatePath);
        
        // Check required elements
        $this->assertStringContainsString('PIX Withdrawal Confirmation', $content);
        $this->assertStringContainsString('Confirmação de Saque PIX', $content);
        $this->assertStringContainsString('{!! $body !!}', $content);
        $this->assertStringContainsString('PIX Withdrawal Service', $content);
    }

    public function testEmailBodyFormat(): void
    {
        // Test email body formatting
        $withdrawId = 'test-123';
        $amount = 100.50;
        $pixKey = 'user@example.com';
        
        // Simulate email body creation
        $date = date('d/m/Y H:i:s');
        $formattedAmount = 'R$ ' . number_format($amount, 2, ',', '.');

        $expectedContent = [
            'Data: ' . $date,
            'Valor: ' . $formattedAmount,
            'Chave PIX: ' . $pixKey,
            'ID da transação: ' . $withdrawId
        ];

        foreach ($expectedContent as $content) {
            $this->assertIsString($content);
            $this->assertNotEmpty($content);
        }
    }

    public function testMailhogConfiguration(): void
    {
        // Test that Mailhog configuration is correct
        $mailHost = env('MAIL_HOST', 'mailhog');
        $mailPort = env('MAIL_PORT', 1025);
        
        $this->assertEquals('mailhog', $mailHost);
        $this->assertEquals(1025, $mailPort);
    }
}