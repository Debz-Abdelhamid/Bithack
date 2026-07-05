<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePasswordPolicy();
        $this->configureRateLimiting();
        $this->configureAuditLogging();
    }

    /**
     * Security.md §2 — minimum length + breached-password check. The HIBP
     * lookup is skipped in the test environment to keep CI offline.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12)->letters()->mixedCase()->numbers();

            return $this->app->environment('testing')
                ? $rule
                : $rule->uncompromised();
        });
    }

    /**
     * Security.md §5 — Redis-backed limiters (cache store is Redis). The
     * panel limiter caps Livewire chatter per user/IP; broadcasting-auth
     * protects the private-channel authorization endpoint.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('panel', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });

        RateLimiter::for('broadcasting-auth', function (Request $request): Limit {
            return Limit::perMinute(30)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });

        // Public QR lookup — the single most likely endpoint to be hammered
        // (mass inventory campaigns, abuse). Per-IP overall + tighter
        // per-token-per-IP, so one hot label can't starve the rest either.
        RateLimiter::for('qr-lookup', function (Request $request): array {
            $token = (string) $request->route('token');

            return [
                Limit::perMinute(30)->by('qr-lookup:ip:'.$request->ip()),
                Limit::perMinute(10)->by('qr-lookup:token:'.$request->ip().':'.$token),
            ];
        });
    }

    /**
     * Security.md §8 — authentication activity and role/permission changes
     * are written to the audit log. Repeated `login_failed` entries are the
     * early abuse signal monitoring should watch.
     */
    private function configureAuditLogging(): void
    {
        Event::listen(function (Login $event): void {
            activity('auth')
                ->causedBy($event->user instanceof Model ? $event->user : null)
                ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                ->log('login');
        });

        Event::listen(function (Logout $event): void {
            activity('auth')
                ->causedBy($event->user instanceof Model ? $event->user : null)
                ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                ->log('logout');
        });

        Event::listen(function (Failed $event): void {
            activity('auth')
                ->withProperties([
                    'ip' => request()->ip(),
                    'email' => Arr::get($event->credentials, 'email'),
                    'guard' => $event->guard,
                ])
                ->log('login_failed');
        });

        Event::listen(function (PasswordReset $event): void {
            activity('auth')
                ->causedBy($event->user instanceof Model ? $event->user : null)
                ->withProperties(['ip' => request()->ip()])
                ->log('password_reset');
        });

        Event::listen(function (RoleAttachedEvent $event): void {
            activity('rbac')
                ->causedBy(auth()->user())
                ->performedOn($event->model)
                ->withProperties(['roles' => $this->normalizeNames($event->rolesOrIds)])
                ->log('roles_attached');
        });

        Event::listen(function (RoleDetachedEvent $event): void {
            activity('rbac')
                ->causedBy(auth()->user())
                ->performedOn($event->model)
                ->withProperties(['roles' => $this->normalizeNames($event->rolesOrIds)])
                ->log('roles_detached');
        });

        Event::listen(function (PermissionAttachedEvent $event): void {
            activity('rbac')
                ->causedBy(auth()->user())
                ->performedOn($event->model)
                ->withProperties(['permissions' => $this->normalizeNames($event->permissionsOrIds)])
                ->log('permissions_attached');
        });

        Event::listen(function (PermissionDetachedEvent $event): void {
            activity('rbac')
                ->causedBy(auth()->user())
                ->performedOn($event->model)
                ->withProperties(['permissions' => $this->normalizeNames($event->permissionsOrIds)])
                ->log('permissions_detached');
        });
    }

    /**
     * Spatie event payloads may hold ids, names, models or collections —
     * normalize to a readable list for the audit trail.
     *
     * @return list<int|string>
     */
    private function normalizeNames(mixed $rolesOrIds): array
    {
        return collect(Arr::wrap($rolesOrIds))
            ->flatten()
            ->map(function (mixed $item): int|string {
                if (is_object($item)) {
                    return $item->name ?? $item->id ?? $item::class;
                }

                return is_int($item) || is_string($item) ? $item : gettype($item);
            })
            ->values()
            ->all();
    }
}
