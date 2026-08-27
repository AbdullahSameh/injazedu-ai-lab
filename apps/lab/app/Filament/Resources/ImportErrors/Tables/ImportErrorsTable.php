<?php

namespace App\Filament\Resources\ImportErrors\Tables;

use App\Models\ImportError;
use App\Models\ImportRun;
use App\Support\Import\ImportErrorCode;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * FR-051: filterable by code, severity and source table; names the run it
 * is displaying and defaults to the latest one that actually wrote rows,
 * with every earlier run still reachable — this table is append-only
 * history, never rewritten by a later clean run (FR-027).
 */
class ImportErrorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label(__('console.import_errors.code'))
                    ->badge(),
                TextColumn::make('severity')
                    ->label(__('console.import_errors.severity'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("console.import_errors.severity_{$state}"))
                    ->color(fn (string $state): string => $state === 'error' ? 'danger' : 'warning'),
                TextColumn::make('source_table')
                    ->label(__('console.import_errors.source_table')),
                TextColumn::make('source_id')
                    ->label(__('console.import_errors.source_id'))
                    ->numeric()
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->label(__('console.import_errors.message'))
                    ->limit(100)
                    ->wrap(),
                TextColumn::make('run.kind')
                    ->label(__('console.import_errors.run')),
                TextColumn::make('created_at')
                    ->label(__('console.import_errors.created_at'))
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('code')
                    ->label(__('console.import_errors.code'))
                    ->options(fn (): array => collect(ImportErrorCode::cases())
                        ->mapWithKeys(fn (ImportErrorCode $code): array => [$code->value => $code->value])
                        ->all())
                    ->default(fn (): ?string => request()->query('code')),
                SelectFilter::make('severity')
                    ->label(__('console.import_errors.severity'))
                    ->options([
                        ImportErrorCode::SEVERITY_ERROR => __('console.import_errors.severity_error'),
                        ImportErrorCode::SEVERITY_WARNING => __('console.import_errors.severity_warning'),
                    ])
                    ->default(fn (): ?string => request()->query('severity')),
                SelectFilter::make('source_table')
                    ->label(__('console.import_errors.source_table'))
                    ->options(fn (): array => ImportError::query()
                        ->distinct()
                        ->orderBy('source_table')
                        ->pluck('source_table', 'source_table')
                        ->all())
                    ->default(fn (): ?string => request()->query('source_table')),
                SelectFilter::make('import_run_id')
                    ->label(__('console.import_errors.run'))
                    ->options(fn (): array => ImportRun::query()
                        ->orderByDesc('started_at')
                        ->get()
                        ->mapWithKeys(fn (ImportRun $run): array => [
                            $run->id => "#{$run->id} · {$run->kind} · {$run->started_at?->toDateTimeString()}",
                        ])
                        ->all())
                    ->default(fn (): ?int => request()->query('run') ?? self::latestRunThatWroteRows()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    /** FR-051's default: the latest run whose insert/update counters are non-zero. */
    private static function latestRunThatWroteRows(): ?int
    {
        return ImportRun::query()
            ->where(fn (Builder $q) => $q->where('rows_inserted', '>', 0)->orWhere('rows_updated', '>', 0))
            ->orderByDesc('started_at')
            ->value('id');
    }
}
