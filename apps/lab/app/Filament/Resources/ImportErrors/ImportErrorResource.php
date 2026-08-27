<?php

namespace App\Filament\Resources\ImportErrors;

use App\Filament\Resources\ImportErrors\Pages\ListImportErrors;
use App\Filament\Resources\ImportErrors\Pages\ViewImportError;
use App\Filament\Resources\ImportErrors\Schemas\ImportErrorInfolist;
use App\Filament\Resources\ImportErrors\Tables\ImportErrorsTable;
use App\Models\ImportError;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** FR-051 — the append-only log, filterable by code/severity/table. Read-only: nothing here is ever fixed or hidden. */
class ImportErrorResource extends Resource
{
    protected static ?string $model = ImportError::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function getNavigationLabel(): string
    {
        return __('console.nav.import_errors');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('console.nav.bank_group');
    }

    public static function getModelLabel(): string
    {
        return __('console.import_errors.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.nav.import_errors');
    }

    public static function infolist(Schema $schema): Schema
    {
        return ImportErrorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportErrorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportErrors::route('/'),
            'view' => ViewImportError::route('/{record}'),
        ];
    }
}
