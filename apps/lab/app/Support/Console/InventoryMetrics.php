<?php

namespace App\Support\Console;

use App\Models\SourceCategory;
use App\Models\SourceCourse;
use App\Models\SourceQuestion;
use App\Models\SourceQuiz;
use App\Models\SourceSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FR-049: every figure here is read from the mirror's own columns —
 * never the source, never `import_errors` — so a no-op re-import cannot
 * make a problem appear to have been fixed. Each method returns raw
 * counts only; suppression (FR-052) and drill-through links are the
 * presentation layer's job, not this one's, so a test can assert a count
 * here reproduces from raw rows (FR-057, SC-016) without pulling in
 * Blade or Filament.
 */
class InventoryMetrics
{
    public function activeQuestionCount(): int
    {
        return SourceQuestion::query()->whereNull('source_deleted_at')->count();
    }

    public function deletedQuestionCount(): int
    {
        return SourceQuestion::query()->whereNotNull('source_deleted_at')->count();
    }

    public function hasHtmlCount(): int
    {
        return SourceQuestion::query()->whereNull('source_deleted_at')->where('has_html', true)->count();
    }

    public function hasImgCount(): int
    {
        return SourceQuestion::query()->whereNull('source_deleted_at')->where('has_img', true)->count();
    }

    public function noExplanationCount(): int
    {
        return SourceQuestion::query()
            ->whereNull('source_deleted_at')
            ->where(fn ($q) => $q->whereNull('explanation_raw')->orWhere('explanation_raw', ''))
            ->count();
    }

    public function mediaReviewCount(): int
    {
        return SourceQuestion::query()->whereNull('source_deleted_at')->where('requires_media_review', true)->count();
    }

    public function sharedTextSectionCount(): int
    {
        return SourceSection::query()->whereNull('source_deleted_at')->where('has_stimulus', true)->count();
    }

    /** @return Collection<int, array{state: string, count: int}> */
    public function answerKeyIntegrity(): Collection
    {
        return SourceQuestion::query()
            ->whereNull('source_deleted_at')
            ->selectRaw('answer_key_state as state, count(*) as count')
            ->groupBy('answer_key_state')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['state' => $row->state, 'count' => (int) $row->count]);
    }

    /** @return Collection<int, array{options_count: int, count: int}> */
    public function optionCountDistribution(): Collection
    {
        return SourceQuestion::query()
            ->whereNull('source_deleted_at')
            ->selectRaw('options_count, count(*) as count')
            ->groupBy('options_count')
            ->orderBy('options_count')
            ->get()
            ->map(fn ($row) => ['options_count' => (int) $row->options_count, 'count' => (int) $row->count]);
    }

    /** @return Collection<int, array{id: int, label: string, count: int}> */
    public function byCategory(): Collection
    {
        $counts = $this->questionsJoinedToQuizzes()
            ->selectRaw('quizzes.category_source_id as category_source_id, count(*) as count')
            ->groupBy('quizzes.category_source_id')
            ->orderByDesc('count')
            ->pluck('count', 'category_source_id');

        $names = SourceCategory::query()->whereIn('source_id', $counts->keys())->pluck('name', 'source_id');

        return $counts->map(fn ($count, $id) => [
            'id' => $id,
            'label' => $names[$id] ?? "#{$id}",
            'count' => (int) $count,
        ])->values();
    }

    /**
     * @return array{rows: Collection<int, array{id: int, label: string, count: int}>, total_groups: int}
     */
    public function byCourse(int $limit = 15): array
    {
        $counts = $this->questionsJoinedToQuizzes()
            ->whereNotNull('quizzes.course_source_id')
            ->selectRaw('quizzes.course_source_id as course_source_id, count(*) as count')
            ->groupBy('quizzes.course_source_id')
            ->orderByDesc('count')
            ->pluck('count', 'course_source_id');

        $names = SourceCourse::query()->whereIn('source_id', $counts->keys())->pluck('name', 'source_id');

        $rows = $counts->map(fn ($count, $id) => [
            'id' => $id,
            'label' => $names[$id] ?? "#{$id}",
            'count' => (int) $count,
        ])->values();

        return ['rows' => $rows->take($limit), 'total_groups' => $rows->count()];
    }

    /**
     * @return array{rows: Collection<int, array{id: int, label: string, count: int}>, total_groups: int}
     */
    public function byQuiz(int $limit = 15): array
    {
        $counts = DB::connection('pgsql')->table('source_questions as questions')
            ->join('source_sections as sections', 'questions.section_source_id', '=', 'sections.source_id')
            ->whereNull('questions.source_deleted_at')
            ->selectRaw('sections.quiz_source_id as quiz_source_id, count(*) as count')
            ->groupBy('sections.quiz_source_id')
            ->orderByDesc('count')
            ->pluck('count', 'quiz_source_id');

        $names = SourceQuiz::query()->whereIn('source_id', $counts->keys())->pluck('name', 'source_id');

        $rows = $counts->map(fn ($count, $id) => [
            'id' => $id,
            'label' => $names[$id] ?? "#{$id}",
            'count' => (int) $count,
        ])->values();

        return ['rows' => $rows->take($limit), 'total_groups' => $rows->count()];
    }

    private function questionsJoinedToQuizzes()
    {
        return DB::connection('pgsql')->table('source_questions as questions')
            ->join('source_sections as sections', 'questions.section_source_id', '=', 'sections.source_id')
            ->join('source_quizzes as quizzes', 'sections.quiz_source_id', '=', 'quizzes.source_id')
            ->whereNull('questions.source_deleted_at');
    }
}
