<?php

namespace App\Filament\Resources\SourceCourses\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SourceCourseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('console.course.name')),
                        TextEntry::make('category.name')
                            ->label(__('console.question.category')),
                        TextEntry::make('source_id')
                            ->label(__('console.course.source_id'))
                            ->numeric(),
                        IconEntry::make('status')
                            ->label(__('console.course.status'))
                            ->boolean(),
                        TextEntry::make('start_date')
                            ->label(__('console.course.start_date'))
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('exam_date')
                            ->label(__('console.course.exam_date'))
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('quizzes_count')
                            ->label(__('console.course.quizzes_count'))
                            ->state(fn ($record): int => $record->quizzes()->count()),
                    ])
                    ->columns(3),
            ]);
    }
}
