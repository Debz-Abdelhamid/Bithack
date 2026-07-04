<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Register;
use App\Http\Middleware\EnforceElevatedIdleTimeout;
use App\Http\Middleware\EnsureElevatedRolesHaveMfa;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Brand teal palette extracted from the legacy Patrimo-BitHack app
     * (ui-design.md §3/§4): MD3 anchors #004c4c / #006666 / #096969 blended
     * with the Tailwind teal interactive shades (#0f766e / #0d9488) the old
     * UI used for links, active nav and controls. Shade 600 is Filament's
     * default interactive shade and maps to the old app's teal-700.
     *
     * @var array<int, string>
     */
    private const PRIMARY_TEAL = [
        50 => '#effefd',
        100 => '#c8fbf8',
        200 => '#a2f0ef',
        300 => '#86d4d3',
        400 => '#3db4b2',
        500 => '#0d9488',
        600 => '#0f766e',
        700 => '#096969',
        800 => '#006666',
        900 => '#004c4c',
        950 => '#002020',
    ];

    public function panel(Panel $panel): Panel
    {
        // Self-service registration (domain-restricted, throttled, verified)
        // can be disabled without a deploy via PATRIMO_REGISTRATION_ENABLED.
        if (config('patrimo.registration.enabled')) {
            $panel->registration(Register::class);
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            // Unverified accounts (self-registered) are held at the
            // verification prompt; admin-provisioned accounts are created
            // pre-verified (Security.md §2).
            ->emailVerification()
            // "Mot de passe oublié" flow — reset links go out by mail
            // (Mailpit locally), throttled by Filament's built-in limits.
            ->passwordReset()
            // Changing one's email from the profile requires verifying the
            // new address before it takes effect.
            ->emailChangeVerification()
            // Custom page: institutional-domain rule on self-service email
            // changes (existing addresses grandfathered until changed).
            ->profile(EditProfile::class)
            // App (TOTP) MFA — mandatory for elevated roles per Security.md §2;
            // other roles may enable it voluntarily from their profile.
            // isRequired must be true at boot so Filament registers the set-up
            // route + middleware; the per-role exemption happens at request
            // time inside EnsureElevatedRolesHaveMfa.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsureElevatedRolesHaveMfa::class)
            ->brandName('Patrimo')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('logo-UBMA.png'))
            ->font('Plus Jakarta Sans')
            ->colors([
                'primary' => self::PRIMARY_TEAL,
                'gray' => Color::Slate,
                'danger' => Color::hex('#ba1a1a'),
                'warning' => Color::Amber,
                'success' => Color::Green,
                'info' => Color::Cyan,
            ])
            // Realtime notification bell (Claude.md §3) — Echo connects to
            // Reverb; 30s polling remains as a graceful fallback.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => app(Vite::class)(['resources/js/echo.js'])->toHtml(),
            )
            ->plugin(FilamentShieldPlugin::make())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Redis-backed panel-wide limiter (Security.md §5).
                'throttle:panel',
            ])
            ->authMiddleware([
                Authenticate::class,
                EnforceElevatedIdleTimeout::class,
            ]);
    }
}
