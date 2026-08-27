<?php

namespace App\Filament\Widgets;

use App\Models\ImportRun;
use App\Models\SourceQuestion;
use App\Models\SourceSnapshot;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The snapshot frame FR-048 originally put on every screen — narrowed to
 * the Dashboard only (amended 2026-08-27, spec.md FR-048/SC-015): Filament
 * auto-renders every discovered widget as the Dashboard's own content
 * grid, which made an explicit render-hook mount on every page a
 * duplicate there too, on top of showing above the breadcrumbs on every
 * Resource page. No render hook is registered for this any more — this
 * class being under app/Filament/Widgets, discovered by
 * AdminPanelProvider's discoverWidgets(), is the only reason it renders.
 */
class SnapshotHeader extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $snapshot = SourceSnapshot::query()->latestRun()->first();
        $lastRun = ImportRun::query()->latest('started_at')->first();

        return [
            Stat::make(
                __('console.snapshot_header.snapshot_taken_at'),
                $snapshot?->snapshot_taken_at?->toDateString() ?? __('console.snapshot_header.none_yet'),
            ),
            Stat::make(
                __('console.snapshot_header.mirrored_questions'),
                number_format(SourceQuestion::query()->count()),
            ),
            Stat::make(
                __('console.snapshot_header.last_import_run'),
                $lastRun?->finished_at?->toDateTimeString()
                    ?? $lastRun?->started_at?->toDateTimeString()
                    ?? __('console.snapshot_header.none_yet'),
            ),
        ];
    }
}
