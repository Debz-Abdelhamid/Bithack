<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Rules\InstitutionalEmailDomain;
use App\Support\RoleName;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;

/**
 * Self-service registration (Security.md §2, user decision 2026-07-04):
 * institutional email domains only, mandatory email verification (panel
 * config), tout_utilisateur role automatically — nothing more — and an hourly
 * per-IP cap on top of Filament's built-in per-minute/per-email limits.
 */
class Register extends BaseRegister
{
    public function register(): ?RegistrationResponse
    {
        $key = 'patrimo-register-ip:'.sha1((string) request()->ip());
        $maxPerHour = (int) config('patrimo.registration.max_per_hour_per_ip');

        if (RateLimiter::tooManyAttempts($key, $maxPerHour)) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'register',
                (string) request()->ip(),
                RateLimiter::availableIn($key),
            ))?->send();

            return null;
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        return parent::register();
    }

    protected function getNameFormComponent(): Component
    {
        $component = parent::getNameFormComponent();

        if ($component instanceof TextInput) {
            $component->label(__('patrimoine.fields.full_name'));
        }

        return $component;
    }

    protected function getEmailFormComponent(): Component
    {
        $component = parent::getEmailFormComponent();

        if (! $component instanceof TextInput) {
            return $component;
        }

        return $component->rules([new InstitutionalEmailDomain]);
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $user = parent::handleRegistration($data);

        // Least privilege: self-registered accounts get exactly one role.
        if ($user instanceof User) {
            $user->assignRole(RoleName::TOUT_UTILISATEUR);
        }

        return $user;
    }
}
