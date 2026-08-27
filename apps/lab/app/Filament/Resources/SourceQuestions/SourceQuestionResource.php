<?php

namespace App\Filament\Resources\SourceQuestions;

use App\Filament\Resources\SourceQuestions\Pages\ListSourceQuestions;
use App\Filament\Resources\SourceQuestions\Pages\ViewSourceQuestion;
use App\Filament\Resources\SourceQuestions\Schemas\SourceQuestionInfolist;
use App\Filament\Resources\SourceQuestions\Tables\SourceQuestionsTable;
use App\Models\SourceQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** The bank's central entity (FR-047, FR-050) — list, filter, and drill into one question's ordered options. Read-only: nothing here writes to the mirror. */
class SourceQuestionResource extends Resource
{
    protected static ?string $model = SourceQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('console.nav.questions');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('console.nav.bank_group');
    }

    public static function getModelLabel(): string
    {
        return __('console.question.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.nav.questions');
    }

    public static function infolist(Schema $schema): Schema
    {
        return SourceQuestionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourceQuestionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceQuestions::route('/'),
            'view' => ViewSourceQuestion::route('/{record}'),
        ];
    }
}
