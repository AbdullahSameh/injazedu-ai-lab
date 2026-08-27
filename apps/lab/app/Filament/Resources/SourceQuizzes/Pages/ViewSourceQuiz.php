<?php

namespace App\Filament\Resources\SourceQuizzes\Pages;

use App\Filament\Resources\SourceQuestions\SourceQuestionResource;
use App\Filament\Resources\SourceQuizzes\SourceQuizResource;
use App\Filament\Resources\SourceSections\SourceSectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSourceQuiz extends ViewRecord
{
    protected static string $resource = SourceQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSections')
                ->label(__('console.quiz.view_sections'))
                ->url(fn (): string => SourceSectionResource::getUrl('index', ['quiz' => $this->record->source_id])),
            Action::make('viewQuestions')
                ->label(__('console.section.view_questions'))
                ->url(fn (): string => SourceQuestionResource::getUrl('index', ['quiz' => $this->record->source_id])),
        ];
    }
}
