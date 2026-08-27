<?php

namespace App\Filament\Resources\ImportErrors\Pages;

use App\Filament\Resources\ImportErrors\ImportErrorResource;
use App\Filament\Resources\SourceQuestions\SourceQuestionResource;
use App\Models\SourceQuestion;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewImportError extends ViewRecord
{
    protected static string $resource = ImportErrorResource::class;

    protected function getHeaderActions(): array
    {
        if ($this->record->source_table !== 'questions' || ! $this->record->source_id) {
            return [];
        }

        $question = SourceQuestion::query()->where('source_id', $this->record->source_id)->first();

        if (! $question) {
            return [];
        }

        return [
            Action::make('viewQuestion')
                ->label(__('console.import_errors.view_question'))
                ->url(SourceQuestionResource::getUrl('view', ['record' => $question])),
        ];
    }
}
