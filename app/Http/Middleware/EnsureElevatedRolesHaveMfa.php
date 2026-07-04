<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/**
 * Security.md §2 — MFA is mandatory only for the roles listed in
 * config/patrimo.php; everyone else may opt in voluntarily. Filament attaches
 * its required-MFA middleware at route-registration time (all or nothing), so
 * the per-role decision has to happen here at request time.
 */
class EnsureElevatedRolesHaveMfa extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->requiresMultiFactorAuthentication()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
