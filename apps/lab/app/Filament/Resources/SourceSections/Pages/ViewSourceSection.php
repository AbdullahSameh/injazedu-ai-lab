<?php

namespace App\Filament\Resources\SourceSections\Pages;

use App\Filament\Resources\SourceQuestions\SourceQuestionResource;
use App\Filament\Resources\SourceSections\SourceSectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSourceSection extends ViewRecord
{
    protected static string $resource = SourceSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewQuestions')
                ->label(__('console.section.view_questions'))
                ->url(fn (): string => SourceQuestionResource::getUrl('index', ['section' => $this->record->source_id])),
        ];
    }
}
