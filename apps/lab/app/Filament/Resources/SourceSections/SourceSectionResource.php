<?php

namespace App\Filament\Resources\SourceSections;

use App\Filament\Resources\SourceSections\Pages\ListSourceSections;
use App\Filament\Resources\SourceSections\Pages\ViewSourceSection;
use App\Filament\Resources\SourceSections\Schemas\SourceSectionInfolist;
use App\Filament\Resources\SourceSections\Tables\SourceSectionsTable;
use App\Models\SourceSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Sections — where the shared stimulus lives (§8, data-model.md §2). Read-only. */
class SourceSectionResource extends Resource
{
    protected static ?string $model = SourceSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('console.nav.sections');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('console.nav.bank_group');
    }

    public static function getModelLabel(): string
    {
        return __('console.section.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.nav.sections');
    }

    public static function infolist(Schema $schema): Schema
    {
        return SourceSectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourceSectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceSections::route('/'),
            'view' => ViewSourceSection::route('/{record}'),
        ];
    }
}
