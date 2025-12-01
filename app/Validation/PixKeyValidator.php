<?php

declare(strict_types=1);

namespace App\Validation;

use App\Dto\ValidationResultDto;

/**
 * PIX key validation class - FOCUSED ON CASE REQUIREMENTS
 * Only validates EMAIL PIX keys as specified in the case
 */
class PixKeyValidator
{
    /**
     * Validate PIX email key
     */
    public function validate(string $email): ValidationResultDto
    {
        $email = trim($email);
        
        if (empty($email)) {
            return ValidationResultDto::invalid([
                ['field' => 'pix_key', 'message' => 'PIX email cannot be empty', 'code' => 'EMPTY_EMAIL']
            ]);
        }

        return $this->validateEmail($email);
    }

    /**
     * Validate CPF format
     */
    private function validateCpf(string $cpf): ValidationResultDto
    {
        // Remove non-numeric characters
        $cpf = preg_replace('/\D/', '', $cpf);

        // Check length
        if (strlen($cpf) !== 11) {
            return ValidationResultDto::invalid([
                ['field' => 'pix_key', 'message' => 'CPF must have 11 digits', 'code' => 'INVALID_CPF_LENGTH']
            ]);
        }

        // Check for repeated digits (like 11111111111)
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return ValidationResultDto::invalid([
                ['field' => 'pix_key', 'message' => 'CPF cannot have all identical digits', 'code' => 'INVALID_CPF_PATTERN']
            ]);
        }

        // Validate CPF algorithm
        if (!$this->isValidCpfAlgorithm($cpf)) {
            return ValidationResultDto::invalid([
                ['field' => 'pix_key', 'message' => 'Invalid CPF format', 'code' => 'INVALID_CPF_ALGORITHM']
            ]);
        }

        return ValidationResultDto::valid();
    }

    /**
     * Validate email format - SIMPLE AND FOCUSED
     */
    private function validateEmail(string $email): ValidationResultDto
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ValidationResultDto::invalid([
                ['field' => 'pix_key', 'message' => 'Invalid email format', 'code' => 'INVALID_EMAIL']
            ]);
        }

        // Basic length check
        if (strlen($email) > 254) {
            return ValidationResultDto::invalid([
                ['field' => 'pix_key', 'message' => 'Email is too long', 'code' => 'EMAIL_TOO_LONG']
            ]);
        }

        return ValidationResultDto::valid();
    }
}