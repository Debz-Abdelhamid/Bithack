<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property array<string>|null $app_authentication_recovery_codes encrypted:array cast
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use LogsActivity;
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'faculty_id',
        'is_active',
        // Set programmatically on trusted provisioning paths (admin create,
        // seeders) — never exposed in any form.
        'email_verified_at',
    ];

    /**
     * Model-level default so freshly created instances (factories,
     * registration) carry the flag before any refetch — the DB column
     * default alone is invisible to the in-memory model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Faculty, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Any active university account may open the panel; what it can
        // actually see/do is entirely permission-driven (Security.md §3).
        // Deactivation (Security.md §13) locks the account out immediately
        // without deleting anything.
        return $this->is_active;
    }

    /**
     * App (TOTP) MFA is mandatory for the roles listed in config/patrimo.php
     * (Security.md §2) — evaluated by the panel's MFA enforcement.
     */
    public function requiresMultiFactorAuthentication(): bool
    {
        return $this->hasAnyRole(config('patrimo.security.mfa_required_roles'));
    }

    /**
     * Elevated roles get a short idle timeout (Security.md §2), enforced by
     * App\Http\Middleware\EnforceElevatedIdleTimeout.
     */
    public function hasElevatedRole(): bool
    {
        return $this->hasAnyRole(config('patrimo.security.elevated_roles'));
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return array<string> | null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string> | null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'faculty_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
