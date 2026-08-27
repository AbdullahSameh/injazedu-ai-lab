<?php

namespace App\Filament\Resources\SourceQuizzes\Tables;

use App\Models\SourceCategory;
use App\Models\SourceCourse;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Navigation from course down to questions runs through here (T082, FR-050). */
class SourceQuizzesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_id')
                    ->label(__('console.quiz.source_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('console.quiz.name'))
                    ->searchable(),
                TextColumn::make('course.name')
                    ->label(__('console.course.title'))
                    ->placeholder(__('console.question.no_course'))
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label(__('console.question.category'))
                    ->toggleable(),
                TextColumn::make('sections_count')
                    ->label(__('console.quiz.sections_count'))
                    ->counts('sections')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('course_source_id')
                    ->label(__('console.course.title'))
                    ->options(fn (): array => SourceCourse::query()->orderBy('name')->pluck('name', 'source_id')->all())
                    ->default(fn (): ?string => request()->query('course')),
                SelectFilter::make('category_source_id')
                    ->label(__('console.question.category'))
                    ->options(fn (): array => SourceCategory::query()->orderBy('name')->pluck('name', 'source_id')->all())
                    ->default(fn (): ?string => request()->query('category')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
