<?php

namespace App\Filament\Resources\SourceSections\Tables;

use App\Models\SourceQuiz;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** §8's basis — shared stimulus text, its length, and whether it counts as "long" (data-model.md §2). */
class SourceSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_id')
                    ->label(__('console.section.source_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('console.section.name'))
                    ->searchable(),
                TextColumn::make('quiz.name')
                    ->label(__('console.quiz.title'))
                    ->toggleable(),
                TextColumn::make('stimulus_length')
                    ->label(__('console.section.stimulus_length'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('has_stimulus')
                    ->label(__('console.section.has_stimulus'))
                    ->boolean(),
                IconColumn::make('is_long_stimulus')
                    ->label(__('console.section.is_long_stimulus'))
                    ->boolean(),
                TextColumn::make('questions_count')
                    ->label(__('console.section.questions_count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('quiz_source_id')
                    ->label(__('console.quiz.title'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => SourceQuiz::query()
                        ->where('name', 'ilike', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'source_id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => SourceQuiz::query()->where('source_id', $value)->value('name'))
                    ->default(fn (): ?string => request()->query('quiz')),
                TernaryFilter::make('has_stimulus')
                    ->label(__('console.section.has_stimulus'))
                    ->default(fn (): ?bool => request()->has('has_stimulus') ? (bool) request()->query('has_stimulus') : null),
                TernaryFilter::make('is_long_stimulus')
                    ->label(__('console.section.is_long_stimulus'))
                    ->default(fn (): ?bool => request()->has('is_long_stimulus') ? (bool) request()->query('is_long_stimulus') : null),
                TernaryFilter::make('deleted')
                    ->label(__('console.question.deleted'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('source_deleted_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('source_deleted_at'),
                        blank: fn (Builder $query): Builder => $query,
                    )
                    ->default(fn (): bool => (bool) request()->query('deleted', false)),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
