<?php

namespace App\Filament\Resources\ImportErrors\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportErrorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('code')
                            ->label(__('console.import_errors.code'))
                            ->badge(),
                        TextEntry::make('severity')
                            ->label(__('console.import_errors.severity'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("console.import_errors.severity_{$state}")),
                        TextEntry::make('source_table')
                            ->label(__('console.import_errors.source_table')),
                        TextEntry::make('source_id')
                            ->label(__('console.import_errors.source_id'))
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('run.kind')
                            ->label(__('console.import_errors.run')),
                        TextEntry::make('created_at')
                            ->label(__('console.import_errors.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(3),
                Section::make(__('console.import_errors.message'))
                    ->schema([
                        TextEntry::make('message')
                            ->label('')
                            ->columnSpanFull(),
                        KeyValueEntry::make('context')
                            ->label(__('console.import_errors.context'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
