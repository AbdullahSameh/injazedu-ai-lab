<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;

/**
 * `quizzes` → `source_quizzes` (data-model.md §2). `sort_order` is spelled
 * correctly in this one source table (notes N4) — copied as `sort_order`
 * directly, never through the `sorte_order` mapping the other four tables
 * use. `user_id` is never selected: quiz-level attribution is lost and
 * that is accepted (§5). `image`, `meta_title`, `meta_description` are
 * likewise never selected.
 */
final class ImportQuizzes extends BankImportJob
{
    protected function sourceTable(): string
    {
        return 'quizzes';
    }

    protected function mirrorTable(): string
    {
        return 'source_quizzes';
    }

    protected function selectColumns(): array
    {
        return [
            'id', 'name', 'slug', 'description', 'sort_order', 'duration', 'hint',
            'course_id', 'category_id', 'lecture_id',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    protected function mapAttributes(object $row): array
    {
        $content = [
            'name' => $row->name,
            'slug' => $row->slug,
            'description' => $row->description,
            'sort_order' => $row->sort_order,
            'duration' => $row->duration,
            'hint' => $row->hint,
            'course_source_id' => $row->course_id,
            'category_source_id' => $row->category_id,
            'lecture_source_id' => $row->lecture_id,
        ];

        return $content + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}
