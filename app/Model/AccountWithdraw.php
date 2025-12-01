<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasOne;
use Carbon\Carbon;

/**
 * AccountWithdraw model representing withdrawal transactions
 * 
 * @property string $id
 * @property string $account_id
 * @property string $method
 * @property float $amount
 * @property bool $scheduled
 * @property Carbon|null $scheduled_for
 * @property string $status
 * @property string|null $error_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $processed_at
 */
class AccountWithdraw extends Model
{
    /**
     * The table associated with the model
     */
    protected ?string $table = 'account_withdraw';

    /**
     * Withdrawal status constants
     */
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_DONE = 'DONE';
    public const STATUS_REJECTED = 'REJECTED';

    /**
     * Withdrawal method constants
     */
    public const METHOD_PIX = 'pix';
    public const METHOD_TED = 'ted';
    public const METHOD_BOLETO = 'boleto';

    /**
     * The attributes that are mass assignable
     */
    protected array $fillable = [
        'id',
        'account_id',
        'method',
        'amount',
        'scheduled',
        'scheduled_for',
        'status',
        'error_reason',

    ];

    /**
     * The attributes that should be cast to native types
     */
    protected array $casts = [
        'amount' => 'decimal:2',
        'scheduled' => 'boolean',
        'scheduled_for' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

    ];

    /**
     * Get the account that owns this withdrawal
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * Get the PIX details for this withdrawal (if method is PIX)
     */
    public function pixDetails(): HasOne
    {
        return $this->hasOne(AccountWithdrawPix::class, 'account_withdraw_id', 'id');
    }

    /**
     * Check if withdrawal is immediate (not scheduled)
     */
    public function isImmediate(): bool
    {
        return !$this->scheduled;
    }

    /**
     * Check if withdrawal is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->scheduled;
    }

    /**
     * Check if withdrawal is ready to be processed
     */
    public function isReadyToProcess(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               (!$this->scheduled || $this->scheduled_for <= Carbon::now());
    }

    /**
     * Check if withdrawal is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    /**
     * Check if withdrawal is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if withdrawal is being processed
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Mark withdrawal as processing
     */
    public function markAsProcessing(): bool
    {
        $this->status = self::STATUS_PROCESSING;
        return $this->save();
    }

    /**
     * Mark withdrawal as completed
     */
    public function markAsCompleted(): bool
    {
        $this->status = self::STATUS_DONE;
        $this->processed_at = Carbon::now();
        return $this->save();
    }

    /**
     * Mark withdrawal as rejected with reason
     */
    public function markAsRejected(string $reason): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->error_reason = $reason;
        $this->processed_at = Carbon::now();
        return $this->save();
    }

    /**
     * Get formatted amount for display
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    /**
     * Scope to find pending withdrawals
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to find scheduled withdrawals ready to process
     */
    public function scopeReadyToProcess($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->where('scheduled', false)
                          ->orWhere(function ($sq) {
                              $sq->where('scheduled', true)
                                 ->where('scheduled_for', '<=', Carbon::now());
                          });
                    });
    }

    /**
     * Scope to find withdrawals by method
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    /**
     * Scope to find withdrawals by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}