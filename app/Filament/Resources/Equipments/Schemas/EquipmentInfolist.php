<?php

namespace App\Filament\Resources\Equipments\Schemas;

use App\Models\Equipment;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class EquipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('patrimoine.sections.identification'))
                    ->columns(3)
                    ->components([
                        TextEntry::make('inventory_code')
                            ->label(__('patrimoine.fields.inventory_code'))
                            ->fontFamily(FontFamily::Mono)
                            ->copyable(),
                        TextEntry::make('designation')
                            ->label(__('patrimoine.fields.designation')),
                        TextEntry::make('category')
                            ->label(__('patrimoine.fields.category')),
                        TextEntry::make('sub_category')
                            ->label(__('patrimoine.fields.sub_category'))
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('patrimoine.fields.status'))
                            ->badge(),
                        TextEntry::make('condition')
                            ->label(__('patrimoine.fields.condition'))
                            ->badge(),
                    ]),
                Section::make(__('patrimoine.sections.details'))
                    ->columns(3)
                    ->components([
                        TextEntry::make('brand')
                            ->label(__('patrimoine.fields.brand'))
                            ->placeholder('—'),
                        TextEntry::make('model')
                            ->label(__('patrimoine.fields.model'))
                            ->placeholder('—'),
                        TextEntry::make('serial_number')
                            ->label(__('patrimoine.fields.serial_number'))
                            ->fontFamily(FontFamily::Mono)
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('patrimoine.fields.notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        ImageEntry::make('photo_path')
                            ->label(__('patrimoine.fields.photo'))
                            ->disk('public')
                            ->visible(fn (Equipment $record): bool => $record->photo_path !== null)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('patrimoine.sections.location_acquisition'))
                    ->columns(3)
                    ->components([
                        TextEntry::make('local.code')
                            ->label(__('patrimoine.fields.local'))
                            ->formatStateUsing(
                                fn (Equipment $record): string => $record->local
                                    ? "{$record->local->code} — {$record->local->name}"
                                    : ''
                            )
                            ->placeholder(__('patrimoine.fields.unplaced')),
                        TextEntry::make('local.building.name')
                            ->label(__('patrimoine.fields.building'))
                            ->placeholder('—'),
                        TextEntry::make('acquisition_date')
                            ->label(__('patrimoine.fields.acquisition_date'))
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('acquisition_value')
                            ->label(__('patrimoine.fields.acquisition_value'))
                            ->money('DZD')
                            ->placeholder('—'),
                        TextEntry::make('warranty_end_date')
                            ->label(__('patrimoine.fields.warranty_end_date'))
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('purchaseReference.external_purchase_id')
                            ->label(__('patrimoine.fields.purchase_reference'))
                            ->placeholder('—'),
                        TextEntry::make('current_assignment')
                            ->label(__('patrimoine.fields.current_assignment'))
                            ->state(function (Equipment $record): ?string {
                                $active = $record->activeAssignment();

                                if ($active === null) {
                                    return null;
                                }

                                $target = collect([
                                    $active->service?->name,
                                    $active->assignedTo?->name,
                                ])->filter()->implode(' · ');

                                if ($target === '') {
                                    $target = (string) $active->local?->code;
                                }

                                return trim($target.' — '.__('patrimoine.fields.since', [
                                    'date' => $active->start_date->format('Y-m-d'),
                                ]));
                            })
                            ->placeholder(__('patrimoine.fields.no_active_assignment')),
                    ]),
                Section::make(__('patrimoine.sections.qr_code'))
                    ->components([
                        ViewEntry::make('qr_code_block')
                            ->view('filament.infolists.equipment-qr')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
