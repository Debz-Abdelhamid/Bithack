<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Rules\InstitutionalEmailDomain;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ->nullable(),
                Select::make('roles')
                    ->label(__('patrimoine.fields.roles'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Role $record): string => __('patrimoine.roles.'.$record->name)
                    )
                    ->multiple()
                    ->preload(),
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
