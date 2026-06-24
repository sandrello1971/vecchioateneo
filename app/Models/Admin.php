<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Admin extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'is_active', 'can_sign_certificates',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_sign_certificates' => 'boolean',
        'password'  => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }

    /**
     * True se l'admin ha 2FA attivo e confermato (setup completato).
     * Verifica direttamente l'attribute raw (saltando l'accessor decrypt)
     * per evitare di fallire se la crypt si rompe per qualche ragione.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->attributes['two_factor_secret'] ?? null)
            && !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Secret TOTP decrypted (per generare QR / verificare codici).
     * Il valore in DB e' Crypt::encryptString del secret raw.
     */
    public function getTwoFactorSecretAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setTwoFactorSecretAttribute(?string $value): void
    {
        $this->attributes['two_factor_secret'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    /**
     * Recovery codes come array. Decrypt + json_decode dal DB.
     */
    public function getTwoFactorRecoveryCodesAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }
        try {
            $decoded = json_decode(Crypt::decryptString($value), true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setTwoFactorRecoveryCodesAttribute(?array $value): void
    {
        $this->attributes['two_factor_recovery_codes'] = $value
            ? Crypt::encryptString(json_encode($value))
            : null;
    }
}
