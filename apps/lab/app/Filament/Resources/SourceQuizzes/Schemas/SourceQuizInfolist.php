<?php

namespace App\Filament\Resources\SourceQuizzes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SourceQuizInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('console.quiz.name')),
                        TextEntry::make('course.name')
                            ->label(__('console.course.title'))
                            ->placeholder(__('console.question.no_course')),
                        TextEntry::make('category.name')
                            ->label(__('console.question.category')),
                        TextEntry::make('source_id')
                            ->label(__('console.quiz.source_id'))
                            ->numeric(),
                        TextEntry::make('duration')
                            ->label(__('console.quiz.duration'))
                            ->placeholder('—'),
                        TextEntry::make('sections_count')
                            ->label(__('console.quiz.sections_count'))
                            ->state(fn ($record): int => $record->sections()->count()),
                    ])
                    ->columns(3),
            ]);
    }
}
