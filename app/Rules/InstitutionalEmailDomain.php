<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Restricts an email address to the institutional domain allowlist
 * (config/patrimo.php — Security.md §2). Exact match on the domain part, so
 * look-alike domains ("univ-annaba.dz.attacker.com") never pass. Applied on
 * every self-service surface that sets an email: registration and the
 * profile's email change.
 */
class InstitutionalEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(substr(strrchr((string) $value, '@') ?: '', 1));

        $allowed = array_map(
            fn (string $allowedDomain): string => strtolower(trim($allowedDomain)),
            config('patrimo.registration.allowed_domains'),
        );

        if (! in_array($domain, $allowed, true)) {
            $fail(__('patrimoine.registration.email_domain', [
                'domains' => implode(', ', $allowed),
            ]));
        }
    }
}
