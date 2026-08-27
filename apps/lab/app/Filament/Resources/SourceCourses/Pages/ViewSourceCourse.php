<?php

namespace App\Filament\Resources\SourceCourses\Pages;

use App\Filament\Resources\SourceCourses\SourceCourseResource;
use App\Filament\Resources\SourceQuizzes\SourceQuizResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSourceCourse extends ViewRecord
{
    protected static string $resource = SourceCourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewQuizzes')
                ->label(__('console.course.view_quizzes'))
                ->url(fn (): string => SourceQuizResource::getUrl('index', ['course' => $this->record->source_id])),
        ];
    }
}
