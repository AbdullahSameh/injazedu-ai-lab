<?php

namespace App\Filament\Resources\SourceSections\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SourceSectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('console.section.name')),
                        TextEntry::make('quiz.name')
                            ->label(__('console.quiz.title')),
                        TextEntry::make('source_id')
                            ->label(__('console.section.source_id'))
                            ->numeric(),
                        TextEntry::make('questions_count')
                            ->label(__('console.section.questions_count'))
                            ->numeric(),
                        IconEntry::make('has_stimulus')
                            ->label(__('console.section.has_stimulus'))
                            ->boolean(),
                        IconEntry::make('is_long_stimulus')
                            ->label(__('console.section.is_long_stimulus'))
                            ->boolean(),
                        TextEntry::make('stimulus_length')
                            ->label(__('console.section.stimulus_length'))
                            ->numeric(),
                    ])
                    ->columns(4),
                Section::make(__('console.section.stimulus'))
                    ->schema([
                        TextEntry::make('stimulus_raw')
                            ->label('')
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
