<?php

namespace App\Filament\Resources\SourceQuestions\Pages;

use App\Filament\Resources\SourceQuestions\SourceQuestionResource;
use Filament\Resources\Pages\ListRecords;

class ListSourceQuestions extends ListRecords
{
    protected static string $resource = SourceQuestionResource::class;
}
