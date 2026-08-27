<?php

namespace App\Filament\Resources\ImportErrors\Pages;

use App\Filament\Resources\ImportErrors\ImportErrorResource;
use App\Models\ImportRun;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

/** FR-051: the header names which import run is on screen. */
class ListImportErrors extends ListRecords
{
    protected static string $resource = ImportErrorResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $runId = $this->tableFilters['import_run_id']['value'] ?? null;

        if (! $runId) {
            return __('console.import_errors.all_runs');
        }

        $run = ImportRun::query()->find($runId);

        if (! $run) {
            return null;
        }

        return __('console.import_errors.showing_run', [
            'id' => $run->id,
            'kind' => $run->kind,
            'started_at' => $run->started_at?->toDateTimeString(),
        ]);
    }
}
