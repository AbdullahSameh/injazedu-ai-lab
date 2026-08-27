<?php

namespace App\Filament\Resources\SourceQuestions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * FR-050: the single-question view, with its options in `option_index`
 * order and the A/B/C/D letters synthesized here — never stored
 * (data-model.md §2).
 */
class SourceQuestionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('console.question.stem'))
                    ->schema([
                        TextEntry::make('raw_text')
                            ->label(__('console.question.stem'))
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('explanation_raw')
                            ->label(__('console.question.explanation'))
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('hint_raw')
                            ->label(__('console.question.hint'))
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('console.question.options'))
                    ->schema([
                        RepeatableEntry::make('options')
                            ->label('')
                            ->schema([
                                TextEntry::make('option_index')
                                    ->label('')
                                    ->formatStateUsing(fn (int $state): string => chr(65 + $state).'.'),
                                TextEntry::make('raw_text')
                                    ->label('')
                                    ->html()
                                    ->columnSpan(2),
                                IconEntry::make('is_correct_derived')
                                    ->label(__('console.question.correct'))
                                    ->boolean(),
                            ])
                            ->columns(4)
                            ->contained(false),
                    ]),
                Section::make(__('console.question.answer_key_state'))
                    ->schema([
                        TextEntry::make('answer_key_state')
                            ->label(__('console.question.answer_key_state'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __("console.answer_key_state.{$state}")),
                        TextEntry::make('correct_option_count')
                            ->label(__('console.question.correct_option_count'))
                            ->numeric(),
                        TextEntry::make('source_id')
                            ->label(__('console.question.source_id'))
                            ->numeric(),
                        TextEntry::make('payload_hash')
                            ->label('payload_hash')
                            ->fontFamily('mono'),
                    ])
                    ->columns(4),
            ]);
    }
}
