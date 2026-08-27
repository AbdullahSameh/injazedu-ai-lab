<?php

namespace App\Filament\Resources\SourceQuizzes;

use App\Filament\Resources\SourceQuizzes\Pages\ListSourceQuizzes;
use App\Filament\Resources\SourceQuizzes\Pages\ViewSourceQuiz;
use App\Filament\Resources\SourceQuizzes\Schemas\SourceQuizInfolist;
use App\Filament\Resources\SourceQuizzes\Tables\SourceQuizzesTable;
use App\Models\SourceQuiz;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Navigation from course and category down to sections and questions (T082). Read-only. */
class SourceQuizResource extends Resource
{
    protected static ?string $model = SourceQuiz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getNavigationLabel(): string
    {
        return __('console.nav.quizzes');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('console.nav.bank_group');
    }

    public static function getModelLabel(): string
    {
        return __('console.quiz.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.nav.quizzes');
    }

    public static function infolist(Schema $schema): Schema
    {
        return SourceQuizInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourceQuizzesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceQuizzes::route('/'),
            'view' => ViewSourceQuiz::route('/{record}'),
        ];
    }
}
