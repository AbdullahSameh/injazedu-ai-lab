<?php

namespace App\Support\Import;

/**
 * The thirteen validation codes and four P2 pipeline codes (FR-042, FR-044,
 * data-model.md §4) — **one** enumeration, so a code has one meaning everywhere it is shown. The console
 * and `lab:import --help` both read this and nothing else; a second list of
 * these strings anywhere is a defect, because the two would drift and the
 * operator would have no way to tell which was current.
 *
 * Severity is a property of the code, not of the site that raises it. It
 * answers one question: *does this affect a student right now?*
 *
 *   - **error** — the question cannot be answered correctly as it stands.
 *     `ZERO_CORRECT` is the headline case (FR-043): a student sitting this
 *     item today cannot get it right, whatever they choose.
 *   - **warning** — the row is suspect and a human should look, but nothing
 *     is broken for a student this minute.
 *
 * `info` exists in the column's domain and is deliberately unused: none of
 * these codes is merely informational, and inventing a use for it would
 * make severity a shrug rather than a judgement.
 *
 * **No code repairs anything** (FR-046). These name what was found; the
 * row is copied faithfully beside the finding, and P2 decides what to do
 * about it.
 */
enum ImportErrorCode: string
{
    case ZERO_CORRECT = 'ZERO_CORRECT';
    case MULTI_CORRECT = 'MULTI_CORRECT';
    case MISSING_OPTIONS = 'MISSING_OPTIONS';
    case EMPTY_STEM = 'EMPTY_STEM';
    case QUESTION_NO_SECTION = 'QUESTION_NO_SECTION';
    case ORPHAN_SECTION = 'ORPHAN_SECTION';
    case ORPHAN_QUIZ = 'ORPHAN_QUIZ';
    case DUPLICATE_OPTION_TEXT = 'DUPLICATE_OPTION_TEXT';
    case OPTION_ORDER_TIE = 'OPTION_ORDER_TIE';
    case BROKEN_HTML = 'BROKEN_HTML';
    case STEM_IMAGE_ONLY = 'STEM_IMAGE_ONLY';
    case STIMULUS_NO_QUESTIONS = 'STIMULUS_NO_QUESTIONS';
    case CATEGORY_ORPHAN_PARENT = 'CATEGORY_ORPHAN_PARENT';

    /** P2 (spec 006-p2-duplicate-intelligence): a truncated embedding input (FR-039, notes.md N4). */
    case EMBEDDING_TRUNCATED = 'EMBEDDING_TRUNCATED';

    /** P2: an embedding call failed — 502 zero_norm_vector or 503 ollama_unreachable (FR-040). */
    case EMBEDDING_FAILED = 'EMBEDDING_FAILED';

    /** P2: a verdict call exhausted its retry budget and is terminally failed (FR-122 – FR-124). */
    case VERDICT_FAILED = 'VERDICT_FAILED';

    /** P2: technical cleanup left a question with no searchable text (FR-027). */
    case EMPTY_SEARCH_TEXT = 'EMPTY_SEARCH_TEXT';

    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public function severity(): string
    {
        return match ($this) {
            self::ZERO_CORRECT,
            self::MISSING_OPTIONS,
            self::EMPTY_STEM,
            self::QUESTION_NO_SECTION,
            self::ORPHAN_SECTION,
            self::ORPHAN_QUIZ => self::SEVERITY_ERROR,

            self::MULTI_CORRECT,
            self::DUPLICATE_OPTION_TEXT,
            self::OPTION_ORDER_TIE,
            self::BROKEN_HTML,
            self::STEM_IMAGE_ONLY,
            self::STIMULUS_NO_QUESTIONS,
            self::CATEGORY_ORPHAN_PARENT,

            // P2's four codes are pipeline-processing failures, not
            // question-content defects — none of them affects a student
            // sitting the item right now, unlike the content-validation codes above.
            self::EMBEDDING_TRUNCATED,
            self::EMBEDDING_FAILED,
            self::VERDICT_FAILED,
            self::EMPTY_SEARCH_TEXT => self::SEVERITY_WARNING,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ZERO_CORRECT => 'No option carries points > 0 — a student cannot answer this correctly.',
            self::MULTI_CORRECT => 'More than one option carries points > 0. Recorded 2026-08-26 as a data-entry error, never an answerable item.',
            self::MISSING_OPTIONS => 'The question has no options at all.',
            self::EMPTY_STEM => 'The question text is empty once tags are stripped, and carries no image either.',
            self::QUESTION_NO_SECTION => 'The question has no section_id — it belongs to no quiz.',
            self::ORPHAN_SECTION => 'The question points at a section_id that does not exist.',
            self::ORPHAN_QUIZ => 'The section points at a quiz_id that does not exist.',
            self::DUPLICATE_OPTION_TEXT => 'Two live options in this question have identical text.',
            self::OPTION_ORDER_TIE => 'Two live options in this question share an `order` value, so their displayed sequence is not determined by the source alone.',
            self::BROKEN_HTML => 'The question text contains unbalanced or unparseable HTML. Logged; the batch continues and the row is copied verbatim.',
            self::STEM_IMAGE_ONLY => 'The question text is an image with no words — it cannot be read, searched, or embedded as text.',
            self::STIMULUS_NO_QUESTIONS => 'The section carries shared stimulus text but has no live questions using it.',
            self::CATEGORY_ORPHAN_PARENT => 'parent_id points at a category that does not exist. Copied as-is; the tree is shown incomplete rather than guessed.',
            self::EMBEDDING_TRUNCATED => 'The embedding input exceeded the model context length and was silently truncated by the runtime; the vector was still computed and stored, flagged as truncated.',
            self::EMBEDDING_FAILED => 'The embedding call failed (a zero-norm vector or an unreachable runtime) and no vector was stored for this attempt; the batch continued.',
            self::VERDICT_FAILED => 'The verdict call exhausted its retry budget. The candidate pair is marked terminally failed and is never re-dispatched.',
            self::EMPTY_SEARCH_TEXT => 'Technical cleanup left this question with no searchable text. Its derived row and hashes were stored, and later grouping excludes it.',
        };
    }
}
