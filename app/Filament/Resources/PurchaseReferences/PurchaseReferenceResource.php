<?php

namespace App\Filament\Resources\PurchaseReferences;

use App\Filament\Resources\PurchaseReferences\Pages\ManagePurchaseReferences;
use App\Models\PurchaseReference;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stub toward module R7 (Schema.md §2.13) — reference-only records so an
 * equipment can point at its purchase order. The full procurement module
 * is out of R13 scope (Phases.md Phase 10).
 */
class PurchaseReferenceResource extends Resource
{
    protected static ?string $model = PurchaseReference::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.purchase_reference.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.purchase_reference.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('external_purchase_id')
                    ->label(__('patrimoine.fields.external_purchase_id'))
                    ->required()
                    ->maxLength(255)
                    ->helperText(__('patrimoine.fields.external_purchase_id_help')),
                TextInput::make('supplier')
                    ->label(__('patrimoine.fields.supplier'))
                    ->maxLength(255),
                DatePicker::make('order_date')
                    ->label(__('patrimoine.fields.order_date')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->withCount('equipments')
            )
            ->columns([
                TextColumn::make('external_purchase_id')
                    ->label(__('patrimoine.fields.external_purchase_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier')
                    ->label(__('patrimoine.fields.supplier'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->label(__('patrimoine.fields.order_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('equipments_count')
                    ->label(__('patrimoine.resources.equipment.plural'))
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ManagePurchaseReferences::route('/'),
        ];
    }
}
