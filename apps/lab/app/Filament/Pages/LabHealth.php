<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Stated placeholder (FR-018, data-model.md §7). المرحلة 7 owns this page's
 * content. No status indicator is fabricated here, and no locale is locked
 * in — P1's first reviewer screen brings Arabic + RTL rather than unpicking
 * a decision made in this increment.
 */
class LabHealth extends Page
{
    protected string $view = 'filament.pages.lab-health';

    protected static ?string $title = 'Lab Health';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'Lab';
}
