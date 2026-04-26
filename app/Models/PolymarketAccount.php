<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolymarketAccount extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'account_slug',
        'wallet_address',
        'funder_address',
        'signature_type',
        'env_key_name',
        'api_key',
        'api_secret',
        'api_passphrase',
        'credential_status',
        'last_error_code',
        'is_active',
        'priority',
        'risk_profile',
        'max_exposure_usd',
        'max_order_size',
        'cooldown_seconds',
        'last_validated_at',
        'last_rotated_at',
        'cooldown_until',
        'auth_failure_count',
        'rate_limit_hit_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_type' => 'integer',
            'api_secret' => 'encrypted',
            'api_passphrase' => 'encrypted',
            'credential_status' => 'string',
            'last_error_code' => 'string',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'risk_profile' => 'string',
            'max_exposure_usd' => 'float',
            'max_order_size' => 'float',
            'cooldown_seconds' => 'integer',
            'last_validated_at' => 'datetime',
            'last_rotated_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'auth_failure_count' => 'integer',
            'rate_limit_hit_count' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'polymarket_account_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PolymarketAccountAuditLog::class, 'polymarket_account_id');
    }
}
