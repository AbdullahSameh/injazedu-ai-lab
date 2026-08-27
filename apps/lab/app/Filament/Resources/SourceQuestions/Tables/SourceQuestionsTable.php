<?php

namespace App\Filament\Resources\SourceQuestions\Tables;

use App\Models\SourceCategory;
use App\Models\SourceCourse;
use App\Models\SourceQuestion;
use App\Models\SourceQuiz;
use App\Models\SourceSection;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The list view for FR-050's drill-down. Every Inventory number links here
 * with a plain `?filterName=value` query string; each filter below reads
 * its own parameter only as its `default()` — the one-time seed for the
 * initial page load — so Filament's own filter state (`tableFilters`,
 * Livewire-bound) is the single source of truth for every request after
 * that, including a reviewer refining the view interactively.
 */
class SourceQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_id')
                    ->label(__('console.question.source_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('raw_text')
                    ->label(__('console.question.stem'))
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('section.quiz.name')
                    ->label(__('console.quiz.title'))
                    ->toggleable(),
                TextColumn::make('answer_key_state')
                    ->label(__('console.question.answer_key_state'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("console.answer_key_state.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'single_correct' => 'success',
                        'broken_no_key' => 'danger',
                        'multi_key' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('options_count')
                    ->label(__('console.question.options_count'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('has_html')
                    ->label(__('console.question.has_html'))
                    ->boolean(),
                IconColumn::make('has_img')
                    ->label(__('console.question.has_img'))
                    ->boolean(),
                IconColumn::make('requires_media_review')
                    ->label(__('console.question.media_review'))
                    ->boolean(),
                TextColumn::make('source_deleted_at')
                    ->label(__('console.question.deleted'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('answer_key_state')
                    ->label(__('console.question.answer_key_state'))
                    ->options(fn (): array => collect(['pending', 'single_correct', 'broken_no_key', 'multi_key'])
                        ->mapWithKeys(fn (string $state): array => [$state => __("console.answer_key_state.{$state}")])
                        ->all())
                    ->default(fn (): ?string => request()->query('answer_key_state')),
                SelectFilter::make('options_count')
                    ->label(__('console.question.options_count'))
                    ->options(fn (): array => SourceQuestion::query()
                        ->distinct()
                        ->orderBy('options_count')
                        ->pluck('options_count', 'options_count')
                        ->all())
                    ->default(fn (): ?string => request()->query('options_count')),
                SelectFilter::make('category')
                    ->label(__('console.question.category'))
                    ->options(fn (): array => SourceCategory::query()->orderBy('name')->pluck('name', 'source_id')->all())
                    ->default(fn (): ?string => request()->query('category'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->whereHas(
                            'section.quiz',
                            fn (Builder $quiz) => $quiz->where('category_source_id', $data['value']),
                        ),
                    )),
                SelectFilter::make('course')
                    ->label(__('console.question.course'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => SourceCourse::query()
                        ->where('name', 'ilike', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'source_id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => SourceCourse::query()->where('source_id', $value)->value('name'))
                    ->default(fn (): ?string => request()->query('course'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->whereHas(
                            'section.quiz',
                            fn (Builder $quiz) => $quiz->where('course_source_id', $data['value']),
                        ),
                    )),
                SelectFilter::make('quiz')
                    ->label(__('console.quiz.title'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => SourceQuiz::query()
                        ->where('name', 'ilike', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'source_id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => SourceQuiz::query()->where('source_id', $value)->value('name'))
                    ->default(fn (): ?string => request()->query('quiz'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->whereHas(
                            'section',
                            fn (Builder $section) => $section->where('quiz_source_id', $data['value']),
                        ),
                    )),
                SelectFilter::make('section')
                    ->label(__('console.section.name'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => SourceSection::query()
                        ->where('name', 'ilike', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'source_id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => SourceSection::query()->where('source_id', $value)->value('name'))
                    ->default(fn (): ?string => request()->query('section'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->where('section_source_id', $data['value']),
                    )),
                TernaryFilter::make('has_html')
                    ->label(__('console.question.has_html'))
                    ->default(fn (): ?bool => request()->has('has_html') ? (bool) request()->query('has_html') : null),
                TernaryFilter::make('has_img')
                    ->label(__('console.question.has_img'))
                    ->default(fn (): ?bool => request()->has('has_img') ? (bool) request()->query('has_img') : null),
                TernaryFilter::make('requires_media_review')
                    ->label(__('console.question.media_review'))
                    ->default(fn (): ?bool => request()->has('requires_media_review') ? (bool) request()->query('requires_media_review') : null),
                TernaryFilter::make('no_explanation')
                    ->label(__('console.question.no_explanation'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('explanation_raw')->orWhere('explanation_raw', '')),
                        false: fn (Builder $query): Builder => $query->whereNotNull('explanation_raw')->where('explanation_raw', '!=', ''),
                        blank: fn (Builder $query): Builder => $query,
                    )
                    ->default(fn (): ?bool => request()->has('no_explanation') ? (bool) request()->query('no_explanation') : null),
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
