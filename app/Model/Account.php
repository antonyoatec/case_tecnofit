<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\HasMany;

/**
 * Account model representing digital account entities
 * 
 * @property string $id
 * @property string $name
 * @property float $balance
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Account extends Model
{
    /**
     * The table associated with the model
     */
    protected ?string $table = 'account';

    /**
     * The attributes that are mass assignable
     */
    protected array $fillable = [
        'id',
        'name',
        'balance',
    ];

    /**
     * The attributes that should be cast to native types
     */
    protected array $casts = [
        'balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all withdrawals for this account
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(AccountWithdraw::class, 'account_id', 'id');
    }

    /**
     * Get pending withdrawals for this account
     */
    public function pendingWithdrawals(): HasMany
    {
        return $this->withdrawals()->where('status', 'PENDING');
    }

    /**
     * Get completed withdrawals for this account
     */
    public function completedWithdrawals(): HasMany
    {
        return $this->withdrawals()->where('status', 'DONE');
    }

    /**
     * Check if account has sufficient balance for withdrawal
     */
    public function hasBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Debit amount from account balance
     */
    public function debit(float $amount): bool
    {
        if (!$this->hasBalance($amount)) {
            return false;
        }

        $this->balance -= $amount;
        return $this->save();
    }

    /**
     * Credit amount to account balance
     */
    public function credit(float $amount): bool
    {
        $this->balance += $amount;
        return $this->save();
    }

    /**
     * Get formatted balance for display
     */
    public function getFormattedBalanceAttribute(): string
    {
        return 'R$ ' . number_format($this->balance, 2, ',', '.');
    }

    /**
     * Scope to find accounts with minimum balance
     */
    public function scopeWithMinimumBalance($query, float $minimumBalance)
    {
        return $query->where('balance', '>=', $minimumBalance);
    }
}