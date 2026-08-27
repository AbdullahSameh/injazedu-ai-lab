<?php

namespace App\Filament\Resources\SourceCourses;

use App\Filament\Resources\SourceCourses\Pages\ListSourceCourses;
use App\Filament\Resources\SourceCourses\Pages\ViewSourceCourse;
use App\Filament\Resources\SourceCourses\Schemas\SourceCourseInfolist;
use App\Filament\Resources\SourceCourses\Tables\SourceCoursesTable;
use App\Models\SourceCourse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Navigation from course down to quizzes and questions (T082). Read-only. */
class SourceCourseResource extends Resource
{
    protected static ?string $model = SourceCourse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function getNavigationLabel(): string
    {
        return __('console.nav.courses');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('console.nav.bank_group');
    }

    public static function getModelLabel(): string
    {
        return __('console.course.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('console.nav.courses');
    }

    public static function infolist(Schema $schema): Schema
    {
        return SourceCourseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourceCoursesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceCourses::route('/'),
            'view' => ViewSourceCourse::route('/{record}'),
        ];
    }
}
