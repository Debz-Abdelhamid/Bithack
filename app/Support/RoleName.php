<?php

namespace App\Support;

/**
 * Canonical role slugs (Security.md §3 — the locked actor set, plus the
 * technical super admin). Roles live in the database as spatie/laravel-permission
 * data; these constants only prevent typos in seeders, config and tests.
 * Business logic must authorize via permissions (`$user->can(...)`), never by
 * comparing these names (Claude.md §4).
 */
final class RoleName
{
    /** Gestionnaire patrimoine (A3) */
    public const GESTIONNAIRE_PATRIMOINE = 'gestionnaire_patrimoine';

    /** Responsable faculté (N2) */
    public const RESPONSABLE_FACULTE = 'responsable_faculte';

    /** Rectorat / Direction (N3) */
    public const RECTORAT = 'rectorat';

    /** Service technique (Étape 5) */
    public const SERVICE_TECHNIQUE = 'service_technique';

    /** Tout utilisateur (generic authenticated staff/students) */
    public const TOUT_UTILISATEUR = 'tout_utilisateur';

    /** Enseignant (teacher — recurring course-slot bookings) */
    public const ENSEIGNANT = 'enseignant';

    /** Technical full-access account (Filament/Shield operational necessity) */
    public const SUPER_ADMIN = 'super_admin';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::GESTIONNAIRE_PATRIMOINE,
            self::RESPONSABLE_FACULTE,
            self::RECTORAT,
            self::SERVICE_TECHNIQUE,
            self::TOUT_UTILISATEUR,
            self::ENSEIGNANT,
            self::SUPER_ADMIN,
        ];
    }
}
