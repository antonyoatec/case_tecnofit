<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\BelongsTo;

/**
 * AccountWithdrawPix model representing PIX-specific withdrawal details
 * 
 * @property string $id
 * @property string $account_withdraw_id
 * @property string $type
 * @property string $pix_key
 * @property \Carbon\Carbon $created_at
 */
class AccountWithdrawPix extends Model
{
    /**
     * The table associated with the model
     */
    protected ?string $table = 'account_withdraw_pix';

    /**
     * PIX key type constants - FOCUSED ON CASE REQUIREMENTS
     * Only EMAIL type as specified in the case
     */
    public const TYPE_CPF = 'CPF';
    public const TYPE_EMAIL = 'EMAIL';
    public const TYPE_PHONE = 'PHONE';
    public const TYPE_RANDOM = 'RANDOM';

    /**
     * The attributes that are mass assignable
     */
    protected array $fillable = [
        'id',
        'account_withdraw_id',
        'type',
        'pix_key',
    ];

    /**
     * The attributes that should be cast to native types
     */
    protected array $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped (only created_at)
     */
    public bool $timestamps = false;

    /**
     * The attributes that should be mutated to dates
     */
    protected array $dates = [
        'created_at',
    ];



    /**
     * Get the withdrawal that owns this PIX details
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(AccountWithdraw::class, 'account_withdraw_id', 'id');
    }

    /**
     * Check if PIX key is CPF type
     */
    public function isCpfType(): bool
    {
        return $this->type === self::TYPE_CPF;
    }

    /**
     * Check if PIX key is email type
     */
    public function isEmailType(): bool
    {
        return $this->type === self::TYPE_EMAIL;
    }

    /**
     * Check if PIX key is phone type
     */
    public function isPhoneType(): bool
    {
        return $this->type === self::TYPE_PHONE;
    }

    /**
     * Check if PIX key is random type
     */
    public function isRandomType(): bool
    {
        return $this->type === self::TYPE_RANDOM;
    }

    /**
     * Get formatted PIX key for display
     */
    public function getFormattedKeyAttribute(): string
    {
        switch ($this->type) {
            case self::TYPE_CPF:
                return $this->formatCpf($this->pix_key);
            case self::TYPE_PHONE:
                return $this->formatPhone($this->pix_key);
            case self::TYPE_EMAIL:
            case self::TYPE_RANDOM:
            default:
                return $this->pix_key;
        }
    }

    /**
     * Format CPF for display
     */
    private function formatCpf(string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    /**
     * Format phone for display
     */
    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        
        if (strlen($phone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
        } elseif (strlen($phone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
        }
        
        return $phone;
    }

    /**
     * Get all valid PIX key types
     */
    public static function getValidTypes(): array
    {
        return [
            self::TYPE_CPF,
            self::TYPE_EMAIL,
            self::TYPE_PHONE,
            self::TYPE_RANDOM,
        ];
    }

    /**
     * Scope to find PIX details by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to find PIX details by key
     */
    public function scopeByKey($query, string $key)
    {
        return $query->where('pix_key', $key);
    }
}