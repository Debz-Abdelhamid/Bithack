<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 5 addendum (2026-07-06) — a faculty manages several departments;
 * N2 administers their own faculty's departments (never deletes — history
 * and reservations may reference them), A3 manages every department.
 */
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.referentiel');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.department.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.department.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('faculty_id')
                    ->label(__('patrimoine.fields.faculty'))
                    ->relationship('faculty', 'name', modifyQueryUsing: fn (Builder $query): Builder => self::scopeFacultyOptions($query))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled($value) && ! self::scopeFacultyOptions(Faculty::query())->whereKey($value)->exists()) {
                                $fail(__('patrimoine.validation.out_of_scope'));
                            }
                        },
                    ]),
                TextInput::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('faculty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->placeholder('—'),
                TextColumn::make('faculty.name')
                    ->label(__('patrimoine.fields.faculty'))
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * N2 may only attach a department to their own faculty (Security.md
     * §3); A3/N3/admin are unrestricted.
     *
     * @param  Builder<Faculty>  $query
     * @return Builder<Faculty>
     */
    public static function scopeFacultyOptions(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && $user->faculty_id !== null && ! $user->can('ViewAcrossFaculties')) {
            $query->whereKey($user->faculty_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }
}
