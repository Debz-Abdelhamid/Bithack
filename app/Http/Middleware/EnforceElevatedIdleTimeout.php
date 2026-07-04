<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security.md §2 — short idle timeout for elevated roles. Regular users keep
 * the standard session lifetime; users holding an elevated role are logged
 * out after config('patrimo.security.elevated_idle_timeout_minutes') of
 * inactivity.
 */
class EnforceElevatedIdleTimeout
{
    private const SESSION_KEY = 'patrimo_elevated_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasElevatedRole()) {
            return $next($request);
        }

        $lastActivity = $request->session()->get(self::SESSION_KEY);
        $timeoutSeconds = (int) config('patrimo.security.elevated_idle_timeout_minutes') * 60;

        if (is_int($lastActivity) && (now()->getTimestamp() - $lastActivity) > $timeoutSeconds) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(Filament::getLoginUrl());
        }

        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }
}
