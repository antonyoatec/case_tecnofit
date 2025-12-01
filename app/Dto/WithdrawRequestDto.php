<?php

declare(strict_types=1);

namespace App\Dto;

use DateTime;

/**
 * DTO for withdrawal request data
 * Similar to Java record or data class
 */
class WithdrawRequestDto
{
    public function __construct(
        public readonly string $method,
        public readonly float $amount,
        public readonly string $pixKey,  // Always email for this case
        public readonly ?DateTime $scheduledFor = null
    ) {
        $this->validate();
    }

    /**
     * Create from array (similar to Jackson deserialization in Java)
     */
    public static function fromArray(array $data): self
    {
        $scheduledFor = null;
        if (!empty($data['scheduled_for'])) {
            $scheduledFor = new DateTime($data['scheduled_for']);
        }

        return new self(
            method: $data['method'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            pixKey: $data['pix_key'] ?? '',  // Email PIX key
            scheduledFor: $scheduledFor
        );
    }

    /**
     * Convert to array (similar to Java serialization)
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'amount' => $this->amount,
            'pix_key' => $this->pixKey,  // Email PIX key
            'scheduled_for' => $this->scheduledFor?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if withdrawal is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->scheduledFor !== null;
    }

    /**
     * Check if withdrawal is immediate
     */
    public function isImmediate(): bool
    {
        return !$this->isScheduled();
    }

    /**
     * Get formatted amount for display
     */
    public function getFormattedAmount(): string
    {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    /**
     * Basic validation (more detailed validation in separate classes)
     */
    private function validate(): void
    {
        if (empty($this->method)) {
            throw new \InvalidArgumentException('Method is required');
        }

        if ($this->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        if (empty($this->pixKey)) {
            throw new \InvalidArgumentException('PIX key is required');
        }

        // PIX type is always EMAIL for this case - no need to validate

        // Validate scheduled date is not in the past
        if ($this->scheduledFor && $this->scheduledFor <= new DateTime()) {
            throw new \InvalidArgumentException('Scheduled date cannot be in the past');
        }
    }
}