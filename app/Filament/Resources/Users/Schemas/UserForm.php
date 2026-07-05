<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Rules\InstitutionalEmailDomain;
use App\Support\RoleName;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('patrimoine.fields.full_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('patrimoine.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    // Institutional allowlist on provisioning too (user
                    // decision 2026-07-04); unchanged emails on existing
                    // records are grandfathered so legacy/demo accounts stay
                    // editable.
                    ->rules([
                        fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($record !== null && strtolower((string) $value) === strtolower($record->email)) {
                                return;
                            }

                            (new InstitutionalEmailDomain)->validate($attribute, $value, $fail);
                        },
                    ]),
                Select::make('faculty_id')
                    ->label(__('patrimoine.fields.faculty'))
                    ->relationship('faculty', 'name')
                    ->preload()
                    ->searchable()
                    ->helperText(__('patrimoine.fields.faculty_help'))
                    // Security.md §3 — faculty is N2's authorization boundary:
                    // required when the responsable_faculte role is selected
                    // (a "nullable" + custom-rule combo would be skipped by the
                    // validator on null values). For teachers/users it is
                    // affiliation metadata only.
                    ->required(function (Get $get): bool {
                        $selectedRoleIds = collect((array) $get('roles'))
                            ->filter()
                            ->map(fn ($id): int => (int) $id);

                        if ($selectedRoleIds->isEmpty()) {
                            return false;
                        }

                        $n2RoleId = Role::query()
                            ->where('name', RoleName::RESPONSABLE_FACULTE)
                            ->value('id');

                        return $n2RoleId !== null && $selectedRoleIds->contains((int) $n2RoleId);
                    })
                    ->validationMessages([
                        'required' => __('patrimoine.fields.faculty_required_for_n2'),
                    ]),
                Select::make('roles')
                    ->label(__('patrimoine.fields.roles'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Role $record): string => __('patrimoine.roles.'.$record->name)
                    )
                    ->multiple()
                    ->preload()
                    ->live(),
                Toggle::make('is_active')
                    ->label(__('patrimoine.fields.is_active'))
                    ->default(true)
                    ->helperText(__('patrimoine.fields.is_active_help')),
                // Never expose MFA secret/recovery columns in any form — the
                // owner manages MFA from their own profile (Security.md §2).
                TextInput::make('password')
                    ->label(__('patrimoine.fields.password'))
                    ->password()
                    ->revealable()
                    ->rule(Password::defaults())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ]);
    }
}
