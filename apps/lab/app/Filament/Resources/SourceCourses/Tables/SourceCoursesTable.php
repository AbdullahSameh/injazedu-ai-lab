<?php

namespace App\Filament\Resources\SourceCourses\Tables;

use App\Models\SourceCategory;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Navigation from course down to quizzes and questions (T082, FR-050). */
class SourceCoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_id')
                    ->label(__('console.course.source_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('console.course.name'))
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label(__('console.question.category'))
                    ->toggleable(),
                IconColumn::make('status')
                    ->label(__('console.course.status'))
                    ->boolean(),
                TextColumn::make('quizzes_count')
                    ->label(__('console.course.quizzes_count'))
                    ->counts('quizzes')
                    ->numeric(),
            ])
            ->filters([
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
