<?php

namespace App\Filament\Pages;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthMatrix;
use Filament\Pages\Page;

class LabHealth extends Page
{
    protected string $view = 'filament.pages.lab-health';

    protected static ?string $title = 'Lab Health';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'Lab';

    /** @var array<int, array<string, int|string>> */
    public array $results = [];

    public bool $hasRun = false;

    public function runHealth(HealthMatrix $matrix): void
    {
        $this->results = array_map(
            fn (CheckResult $result) => $result->toArray(),
            $matrix->run(),
        );
        $this->hasRun = true;
    }
}
