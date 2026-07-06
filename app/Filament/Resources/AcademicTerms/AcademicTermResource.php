<?php

namespace App\Filament\Resources\AcademicTerms;

use App\Filament\Resources\AcademicTerms\Pages\ManageAcademicTerms;
use App\Models\AcademicTerm;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Phase 5 addendum (2026-07-06) — the academic year splits into 2
 * semesters; A3 manages this university-wide referential (like Faculty),
 * N2/N3 read it to pick a term when filling/reviewing a timetable.
 */
class AcademicTermResource extends Resource
{
    protected static ?string $model = AcademicTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.referentiel');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.academic_term.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.academic_term.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('academic_year')
                    ->label(__('patrimoine.fields.academic_year'))
                    ->required()
                    ->placeholder('2026-2027')
                    ->maxLength(255),
                Select::make('semester')
                    ->label(__('patrimoine.fields.semester'))
                    ->options([1 => __('patrimoine.fields.semester_1'), 2 => __('patrimoine.fields.semester_2')])
                    ->required(),
                TextInput::make('label')
                    ->label(__('patrimoine.fields.label'))
                    ->maxLength(255)
                    ->helperText(__('patrimoine.fields.term_label_help')),
                DatePicker::make('start_date')
                    ->label(__('patrimoine.fields.start_date'))
                    ->required(),
                DatePicker::make('end_date')
                    ->label(__('patrimoine.fields.end_date'))
                    ->required()
                    ->after('start_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('label')
                    ->label(__('patrimoine.fields.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label(__('patrimoine.fields.start_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('patrimoine.fields.end_date'))
                    ->date()
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

    public static function getPages(): array
    {
        return [
            'index' => ManageAcademicTerms::route('/'),
        ];
    }
}
