<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Rules\InstitutionalEmailDomain;
use Closure;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Self-service email changes must respect the institutional domain allowlist
 * (Security.md §2) — otherwise the registration gate could be bypassed by
 * registering with a valid address and swapping it afterwards. Existing
 * addresses are grandfathered: the rule only fires when the value actually
 * changes (admin-provisioned accounts, e.g. demo ones, keep saving their
 * profile untouched).
 */
class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        $component = parent::getEmailFormComponent();

        if (! $component instanceof TextInput) {
            return $component;
        }

        return $component
            ->label(__('patrimoine.fields.email'))
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $current = auth()->user();

                    if ($current instanceof User && strtolower((string) $value) === strtolower($current->email)) {
                        return;
                    }

                    (new InstitutionalEmailDomain)->validate($attribute, $value, $fail);
                },
            ]);
    }

    protected function getNameFormComponent(): Component
    {
        $component = parent::getNameFormComponent();

        if ($component instanceof TextInput) {
            $component->label(__('patrimoine.fields.full_name'));
        }

        return $component;
    }
}
