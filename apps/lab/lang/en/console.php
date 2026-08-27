<?php

/**
 * FR-047: reviewer-facing text is English here; technical identifiers
 * (`payload_hash`, `answer_key_state`'s raw values, the thirteen import
 * error codes) stay English in both locales and are never translated.
 */
return [

    'nav' => [
        'bank_group' => 'Question Bank',
        'inventory' => 'Inventory',
        'questions' => 'Questions',
        'sections' => 'Sections',
        'quizzes' => 'Quizzes',
        'courses' => 'Courses',
        'import_errors' => 'Import Errors',
    ],

    'snapshot_header' => [
        'snapshot_taken_at' => 'Snapshot taken at',
        'mirrored_questions' => 'Mirrored questions',
        'last_import_run' => 'Last import run',
        'none_yet' => 'None yet',
    ],

    'suppression' => [
        'partial' => '10–29 (masked)',
        'hidden' => '—',
    ],

    'answer_key_state' => [
        'pending' => 'Pending',
        'single_correct' => 'Single correct answer',
        'broken_no_key' => 'No correct answer',
        'multi_key' => 'More than one correct answer',
    ],

    'inventory' => [
        'title' => 'Bank inventory',
        'quality_cards' => 'Bank quality',
        'active' => 'Active questions',
        'soft_deleted' => 'Soft-deleted questions',
        'no_explanation' => 'No explanation',
        'has_html' => 'Contains HTML',
        'has_img' => 'Contains image',
        'media_review' => 'Needs media review',
        'shared_text_sections' => 'Shared-text sections',
        'answer_key_integrity' => 'Answer key integrity',
        'option_count_distribution' => 'Option count distribution',
        'by_category' => 'By category',
        'by_course' => 'By course',
        'by_quiz' => 'By quiz',
        'view_all' => 'View all (:count)',
        'view_import_errors' => 'Go to the import errors screen',
        'limits_title' => 'What this screen cannot confirm',
        'limits_media' => 'Media paths are unverified — a referenced file is not guaranteed to exist.',
        'limits_answer_gap' => 'A missing answer row does not distinguish "not answered" from "never shown to the student".',
    ],

    'question' => [
        'singular' => 'Question',
        'source_id' => 'ID',
        'stem' => 'Stem',
        'explanation' => 'Explanation',
        'hint' => 'Hint',
        'options' => 'Options',
        'correct' => 'Correct?',
        'correct_option_count' => 'Correct option count',
        'answer_key_state' => 'Answer key state',
        'options_count' => 'Option count',
        'has_html' => 'Contains HTML',
        'has_img' => 'Contains image',
        'media_review' => 'Needs media review',
        'no_explanation' => 'No explanation',
        'deleted' => 'Deleted',
        'category' => 'Category',
        'course' => 'Course',
        'no_course' => '(no course)',
    ],

    'section' => [
        'singular' => 'Section',
        'source_id' => 'ID',
        'name' => 'Name',
        'stimulus' => 'Shared text',
        'stimulus_length' => 'Shared text length',
        'has_stimulus' => 'Has shared text?',
        'is_long_stimulus' => 'Long text?',
        'questions_count' => 'Question count',
        'view_questions' => "View this section's questions",
    ],

    'quiz' => [
        'singular' => 'Quiz',
        'source_id' => 'ID',
        'title' => 'Quiz',
        'name' => 'Name',
        'duration' => 'Duration',
        'sections_count' => 'Section count',
        'view_sections' => "View this quiz's sections",
    ],

    'course' => [
        'singular' => 'Course',
        'source_id' => 'ID',
        'title' => 'Course',
        'name' => 'Name',
        'status' => 'Active?',
        'start_date' => 'Start date',
        'exam_date' => 'Exam date',
        'quizzes_count' => 'Quiz count',
        'view_quizzes' => "View this course's quizzes",
    ],

    'import_errors' => [
        'singular' => 'Import error',
        'code' => 'Code',
        'severity' => 'Severity',
        'severity_error' => 'Error',
        'severity_warning' => 'Warning',
        'source_table' => 'Table',
        'source_id' => 'ID',
        'message' => 'Message',
        'context' => 'Context',
        'run' => 'Import run',
        'created_at' => 'Logged at',
        'all_runs' => 'All import runs',
        'showing_run' => 'Showing run #:id (:kind) — started :started_at',
        'view_question' => 'View question',
    ],

];
