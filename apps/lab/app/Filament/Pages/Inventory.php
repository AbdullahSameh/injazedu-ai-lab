<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ImportErrors\ImportErrorResource;
use App\Filament\Resources\SourceCourses\SourceCourseResource;
use App\Filament\Resources\SourceQuestions\SourceQuestionResource;
use App\Filament\Resources\SourceQuizzes\SourceQuizResource;
use App\Filament\Resources\SourceSections\SourceSectionResource;
use App\Support\Console\InventoryMetrics;
use App\Support\Suppression;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * FR-049/FR-050: the bank's shape, sourced entirely from the mirror's own
 * columns. Every card is built by {@see stat()}, which applies FR-052's
 * suppression rule and only attaches a drill-through link once a count is
 * fully published — there is nothing exact to link a hidden or bucketed
 * figure through to.
 */
class Inventory extends Page
{
    protected string $view = 'filament.pages.inventory';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -1;

    /** @var array<string, mixed> */
    public array $cards = [];

    /** @var array<int, array{id: int|string, label: string, count: int}> */
    public array $byCategory = [];

    /** @var array{rows: array<int, array{id: int|string, label: string, count: int}>, total_groups: int} */
    public array $byCourse = [];

    /** @var array{rows: array<int, array{id: int|string, label: string, count: int}>, total_groups: int} */
    public array $byQuiz = [];

    /** @var array<int, array{state: string, count: int}> */
    public array $answerKeyIntegrity = [];

    /** @var array<int, array{options_count: int, count: int}> */
    public array $optionCountDistribution = [];

    public function getTitle(): string|Htmlable
    {
        return __('console.inventory.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('console.nav.inventory');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('console.nav.bank_group');
    }

    public function mount(): void
    {
        $metrics = new InventoryMetrics;

        $this->cards = [
            'active' => $this->stat(
                $metrics->activeQuestionCount(),
                __('console.inventory.active'),
                SourceQuestionResource::getUrl('index'),
            ),
            'soft_deleted' => $this->stat(
                $metrics->deletedQuestionCount(),
                __('console.inventory.soft_deleted'),
                SourceQuestionResource::getUrl('index', ['deleted' => 1]),
            ),
            'no_explanation' => $this->stat(
                $metrics->noExplanationCount(),
                __('console.inventory.no_explanation'),
                SourceQuestionResource::getUrl('index', ['no_explanation' => 1]),
            ),
            'has_html' => $this->stat(
                $metrics->hasHtmlCount(),
                __('console.inventory.has_html'),
                SourceQuestionResource::getUrl('index', ['has_html' => 1]),
            ),
            'has_img' => $this->stat(
                $metrics->hasImgCount(),
                __('console.inventory.has_img'),
                SourceQuestionResource::getUrl('index', ['has_img' => 1]),
            ),
            'media_review' => $this->stat(
                $metrics->mediaReviewCount(),
                __('console.inventory.media_review'),
                SourceQuestionResource::getUrl('index', ['requires_media_review' => 1]),
            ),
            'shared_text_sections' => $this->stat(
                $metrics->sharedTextSectionCount(),
                __('console.inventory.shared_text_sections'),
                SourceSectionResource::getUrl('index', ['has_stimulus' => 1]),
            ),
        ];

        $this->answerKeyIntegrity = $metrics->answerKeyIntegrity()
            ->map(function (array $row): array {
                $label = __("console.answer_key_state.{$row['state']}");

                return [
                    ...$row,
                    ...$this->stat($row['count'], $label, SourceQuestionResource::getUrl('index', ['answer_key_state' => $row['state']])),
                ];
            })
            ->all();

        $this->optionCountDistribution = $metrics->optionCountDistribution()
            ->map(fn (array $row): array => [
                ...$row,
                ...$this->stat($row['count'], (string) $row['options_count'], SourceQuestionResource::getUrl('index', ['options_count' => $row['options_count']])),
            ])
            ->all();

        $this->byCategory = $metrics->byCategory()
            ->map(fn (array $row): array => [
                ...$row,
                ...$this->stat($row['count'], $row['label'], SourceQuestionResource::getUrl('index', ['category' => $row['id']])),
            ])
            ->all();

        $byCourse = $metrics->byCourse();
        $this->byCourse = [
            'rows' => $byCourse['rows']->map(fn (array $row): array => [
                ...$row,
                ...$this->stat($row['count'], $row['label'], SourceQuestionResource::getUrl('index', ['course' => $row['id']])),
            ])->all(),
            'total_groups' => $byCourse['total_groups'],
            'view_all_url' => SourceCourseResource::getUrl('index'),
        ];

        $byQuiz = $metrics->byQuiz();
        $this->byQuiz = [
            'rows' => $byQuiz['rows']->map(fn (array $row): array => [
                ...$row,
                ...$this->stat($row['count'], $row['label'], SourceQuestionResource::getUrl('index', ['quiz' => $row['id']])),
            ])->all(),
            'total_groups' => $byQuiz['total_groups'],
            'view_all_url' => SourceQuizResource::getUrl('index'),
        ];
    }

    public function getImportErrorsUrl(): string
    {
        return ImportErrorResource::getUrl('index');
    }

    /** @return array{n: int, display: string, linkable: bool, label: string, url: string} */
    private function stat(int $n, string $label, string $url): array
    {
        return [
            'n' => $n,
            'display' => Suppression::display($n),
            'linkable' => Suppression::isLinkable($n),
            'label' => $label,
            'url' => $url,
        ];
    }
}
