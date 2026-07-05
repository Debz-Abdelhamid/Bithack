<?php

namespace App\Filament\Resources\Equipments\Schemas;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\Local;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('patrimoine.sections.identification'))
                    ->columns(2)
                    ->components([
                        TextInput::make('inventory_code')
                            ->label(__('patrimoine.fields.inventory_code'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('patrimoine.fields.inventory_code_help')),
                        TextInput::make('designation')
                            ->label(__('patrimoine.fields.designation'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('category')
                            ->label(__('patrimoine.fields.category'))
                            ->required()
                            ->maxLength(255)
                            ->datalist(fn (): array => self::knownCategories()),
                        TextInput::make('sub_category')
                            ->label(__('patrimoine.fields.sub_category'))
                            ->maxLength(255),
                    ]),
                Section::make(__('patrimoine.sections.details'))
                    ->columns(2)
                    ->components([
                        TextInput::make('brand')
                            ->label(__('patrimoine.fields.brand'))
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label(__('patrimoine.fields.model'))
                            ->maxLength(255),
                        TextInput::make('serial_number')
                            ->label(__('patrimoine.fields.serial_number'))
                            ->maxLength(255),
                        Select::make('condition')
                            ->label(__('patrimoine.fields.condition'))
                            ->options(EquipmentCondition::class)
                            ->default(EquipmentCondition::New)
                            ->required(),
                        Select::make('status')
                            ->label(__('patrimoine.fields.status'))
                            ->options(EquipmentStatus::class)
                            ->default(EquipmentStatus::InService)
                            ->required(),
                        FileUpload::make('photo_path')
                            ->label(__('patrimoine.fields.photo'))
                            ->disk('public')
                            ->directory('equipment-photos')
                            ->image()
                            ->maxSize(4096),
                        Textarea::make('notes')
                            ->label(__('patrimoine.fields.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('patrimoine.sections.location_acquisition'))
                    ->columns(2)
                    ->components([
                        Select::make('local_id')
                            ->label(__('patrimoine.fields.local'))
                            ->relationship('local', 'name')
                            ->getOptionLabelFromRecordUsing(
                                fn (Local $record): string => "{$record->code} — {$record->name}"
                            )
                            ->searchable(['code', 'name'])
                            ->preload()
                            ->nullable()
                            ->helperText(__('patrimoine.fields.equipment_local_help')),
                        DatePicker::make('acquisition_date')
                            ->label(__('patrimoine.fields.acquisition_date')),
                        TextInput::make('acquisition_value')
                            ->label(__('patrimoine.fields.acquisition_value'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('DZD'),
                        DatePicker::make('warranty_end_date')
                            ->label(__('patrimoine.fields.warranty_end_date')),
                        Select::make('purchase_reference_id')
                            ->label(__('patrimoine.fields.purchase_reference'))
                            ->relationship('purchaseReference', 'external_purchase_id')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(__('patrimoine.fields.purchase_reference_help'))
                            ->createOptionForm([
                                TextInput::make('external_purchase_id')
                                    ->label(__('patrimoine.fields.external_purchase_id'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('supplier')
                                    ->label(__('patrimoine.fields.supplier'))
                                    ->maxLength(255),
                                DatePicker::make('order_date')
                                    ->label(__('patrimoine.fields.order_date')),
                            ]),
                    ]),
            ]);
    }

    /**
     * Suggested categories from Schema.md §2.8 (electrique, plomberie,
     * informatique, mobilier…) — free text stays allowed, the taxonomy is
     * not locked yet.
     *
     * @return list<string>
     */
    private static function knownCategories(): array
    {
        return ['informatique', 'mobilier', 'electrique', 'plomberie', 'audiovisuel', 'securite'];
    }
}
