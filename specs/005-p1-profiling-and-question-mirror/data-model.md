# Data Model — P1 Mirror Schema

**Date**: 2026-08-25 · **Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md) ·
**Findings**: [notes.md](./notes.md)

Fourteen Lab-owned PostgreSQL tables. Every column below is checked against
`docs/schema/injazedu-db-schema.md`; **where the P1 plan and the measured schema disagreed, the
schema won** and the difference is noted.

This file exists because the mirror schema shape is one of the three things the constitution calls
expensive to reverse once data exists (Principle I). It is worth pinning before the first migration.

---

## 1. The common columns

Every mirror table (the twelve `source_*` tables; not `import_runs` / `import_errors`):

| Column | Type | Note |
|---|---|---|
| `source_system` | TEXT | constant `'injazedu_production'` |
| `source_id` | BIGINT | the Production id **as-is**, never regenerated |
| `source_created_at` | TIMESTAMPTZ NULL | source `created_at` — nullable in the source |
| `source_updated_at` | TIMESTAMPTZ NULL | source `updated_at` |
| `source_deleted_at` | TIMESTAMPTZ NULL | soft-deleted rows are **copied**, not excluded |
| `imported_at` | TIMESTAMPTZ | when this row was last written |
| `import_run_id` | BIGINT | which run last wrote it |
| `payload_hash` | CHAR(64) | SHA256; the basis of idempotency |

`UNIQUE (source_system, source_id)` on every one — the upsert target.

**`source_deleted_at` is structurally always NULL on `source_media` and `source_answers`**
(notes N3): `quiz_files` and `question_result` have no soft delete. The column stays for uniformity;
the migration says why it can never fill.

**`payload_hash` rule** (spec clarification, FR-018): every table hashes a key-sorted JSON of **its
own copied source columns**. `source_questions` is the **only** exception and uses §16's definition
verbatim — `name`, `description`, `hint`, and the options ordered by `option_index` with `name` and
`points` — so editing an option changes the question's hash.

**There is no `user_id` column in any of the fourteen tables.**

---

## 2. The tables

### `source_snapshots` — the register (not a mirror; no common columns)

```text
id · snapshot_taken_at DATE · loaded_at TIMESTAMPTZ · mysql_version TEXT
source_database_size_mb NUMERIC
source_row_counts     JSONB   -- the 11 copyable tables at profiling time
profiling_results     JSONB   -- all 18 query results — THE authoritative record
profiling_report_path TEXT    -- docs/reports/p1-profiling.md
notes TEXT · created_at
```

One row **per profiling run** against the fixed 2026-08-07 snapshot, so a re-run compares rather
than overwrites (FR-006). `import_runs.snapshot_id` points at the most recent row.

### `source_categories`

`name` · `slug` · `sort_order` ← `categories.sorte_order` (sic) · `parent_source_id` ·
`image` + common.

`parent_id` is **INT** while `id` is **BIGINT UNSIGNED**, with **no FK** (§9 item 2). Copied as-is
into `parent_source_id`; orphans logged `CATEGORY_ORPHAN_PARENT`, cycles logged, **neither
repaired**. Not copied: `meta_*`, `courses_card`, `quizzes_card`, `events_card`, `mobile_image`.

### `source_courses` — metadata only

`name` · `slug` · `category_source_id` · `status` · `start_date` · `exam_date` ·
`telegram_channel` · `telegram_group` · `telegram_private` + common.

**Not copied**: `price`, `discount`, `description`, `course_conditions`, `meta_*`, `image`,
`poster`, `schedule`, `intro`, `live_*`, `expire_duration`, `start_date_hijri`, `sorte_order`.
`description` is NOT NULL in the source (notes N7) — omitting it is a decision, recorded in the
migration.

### `source_chapters` · `source_lectures` — title and order only

```text
source_chapters : title · sort_order ← sorte_order · course_source_id + common
source_lectures : topic · sort_order ← sorte_order · chapter_source_id + common
```

**Not copied from `lectures`**: `zoom_start_url`, `zoom_join_url`, `meeting_id`, `passcode`,
`meeting_type`, `vimeo_id`, `bunny_id`, `youtube_id`, `upload_status`, `upload_error`, `host`,
`live`, `book`, `start_time`, `start_date_hijri`, `duration`. Some are credentials; none is about a
question.

### `source_quizzes`

`name` · `slug` · `description` · `status` · `sort_order` (**spelled correctly in the source** —
notes N4) · `duration` · `hint` · `course_source_id` (NULL ⇒ general/open quiz) ·
`category_source_id` · `lecture_source_id` + common.

**`user_id` is not copied** — the quiz author is an FK into `users`. Attribution is lost at the quiz
level and that is accepted (§5: there is no author at the question level). Not copied: `image`,
`meta_*`.

### `source_sections` — where the stimulus lives (§8)

`quiz_source_id` · `name` · `order` + common, plus derived:

| Derived | Rule |
|---|---|
| `stimulus_raw` | faithful copy of `sections.description` |
| `stimulus_length` | `CHAR_LENGTH(stimulus_raw)` |
| `has_stimulus` | `stimulus_raw` non-empty |
| `is_long_stimulus` | `stimulus_length > 200` (query 12's threshold) |
| `questions_count` | second pass, after `source_questions` exists |

`description` is **absent from `Section::$fillable`** (§9 item 7) but the column exists and may be
populated. **Read it; never assume it is empty.** This field is the whole substance of §8.

### `source_questions` — the central table

`section_source_id` · `order` · `raw_text` ← `questions.name` (LONGTEXT, unmodified) ·
`explanation_raw` ← `description` · `hint_raw` ← `hint` + common, plus derived:

| Derived | Rule |
|---|---|
| `correct_option_count` | count of live options with `points > 0` — mechanical, set in the copy pass |
| `answer_key_state` | `pending` → `single_correct` \| `broken_no_key` \| `multi_key`. **Defaults to `pending`**; set only by the backfill pass, after FR-061's decision |
| `options_count` | live options |
| `stem_char_length` | length of `raw_text` |
| `has_html` | contains `<` |
| `has_img` | contains `<img` |
| `is_stem_image_only` | no text after stripping tags |
| `requires_media_review` | second pass — has a `quiz_files` row of type audio/video |
| `source_origin` | `unknown` \| `authored` \| `book_derived` \| `suspected_official`. **Defaults to `unknown`**; nothing else claimed without evidence (§14.5) |

There is **no status column** in the source (§9 item 10). The Lab's status is the only status.

### `source_question_options`

`question_source_id` · `raw_text` ← `options.name` · `points` + common, plus derived:

| Derived | Rule |
|---|---|
| `source_order` | `options.order` as-is — **defaults to 0 and repeats** |
| `option_index` | `` ORDER BY `order` ASC, id ASC `` — mandatory, never abbreviated |
| `is_correct_derived` | `points > 0` (there is no correctness column — §5.1) |

A/B/C/D letters **do not exist in the source** and are **never stored**; they are synthesized from
`option_index` at render time only.

### `source_media` — from `quiz_files`

`type` ENUM(video,image,audio) · `path` (nullable in the source) · `section_source_id` ·
`question_source_id` · `attach_level` ENUM(section,question) · `path_unverified BOOL` + common.

`path_unverified` is **always true** — Production storage is unreachable locally, and
`quiz_files` has no soft delete, so a row may point at a file that is permanently gone. Images
inside `questions.name` are a **second, independent** media path, detected via `has_img`.

### `source_results` — behavioural, pseudonymized

`quiz_source_id` · `total_points` · `student_ref CHAR(64)` · `attempt_index` ·
`duration_estimate_seconds` + common (`source_deleted_at` **does** populate here).

| Derived | Rule |
|---|---|
| `student_ref` | `HMAC-SHA256(pepper, user_id)`. `user_id` is read, hashed and discarded in the same statement |
| `attempt_index` | `ROW_NUMBER() OVER (PARTITION BY student_ref, quiz_source_id ORDER BY source_created_at)` — computed **in Postgres**, second pass |
| `duration_estimate_seconds` | `updated_at − created_at`, **labelled an approximation in the column name** so it is never read as a real duration |

### `source_answers` — from `question_result`

`result_source_id` · `question_source_id` · `option_source_id` · `points` ·
`is_correct_derived` (`points > 0`) + common (`source_deleted_at` never populates).

**Structural limit, recorded not worked around**: `question_result.option_id` is **NOT NULL**, so a
skipped question produces **no row at all**. "No answer" cannot be distinguished from "not shown".
And because `question_result` has no soft delete while `results` does, **exclusion of deleted
attempts must go through `source_results`** (notes N3).

### `import_runs` · `import_errors`

```text
import_runs   : id · snapshot_id · kind (profile|bank|behaviour|backfill)
                started_at · finished_at · status (running|completed|failed|resumed)
                rows_read · rows_inserted · rows_updated · rows_unchanged
                error_count · elapsed_seconds · resume_cursor JSONB · ran_via (inline|queue)

import_errors : id · import_run_id · source_table · source_id
                severity (info|warning|error) · code · message · context JSONB · created_at
```

`import_errors` is **append-only, scoped by `import_run_id`, never deleted or rewritten** (spec
clarification, FR-027). A run that writes nothing logs nothing — which is exactly why the console's
quality cards read the mirror's own columns and never this table.

`context` is JSONB and is filled by code written under pressure. **A `user_id` in an error payload is
a PII leak that no column assertion catches**, so hashing happens at read time, before any error path
can see the raw value.

---

## 3. Indexes — earned, not assumed

```text
source_questions(section_source_id)          source_answers(question_source_id)
source_question_options(question_source_id)  source_answers(result_source_id)
source_results(quiz_source_id)               source_results(student_ref)
```

Plus the fourteen UNIQUE constraints on (`source_system`, `source_id`), which the upsert needs.

**No vector index and no trigram index** — there is nothing to index yet (constitution VII).

---

## 4. The thirteen validation codes

| Code | Severity | Condition |
|---|---|---|
| `ZERO_CORRECT` | **error** | `correct_option_count = 0` — affects a student now |
| `MULTI_CORRECT` | warning | `correct_option_count > 1` |
| `MISSING_OPTIONS` | error | a question with no options |
| `EMPTY_STEM` | error | stem empty after stripping tags |
| `QUESTION_NO_SECTION` | error | `section_id` is NULL |
| `ORPHAN_SECTION` | error | question → nonexistent section |
| `ORPHAN_QUIZ` | error | section → nonexistent quiz |
| `DUPLICATE_OPTION_TEXT` | warning | two options with identical text in one question |
| `OPTION_ORDER_TIE` | warning | repeated `order` within a question (options only — notes N6) |
| `BROKEN_HTML` | warning | unbalanced tags in `raw_text` — **never stops the batch** |
| `STEM_IMAGE_ONLY` | warning | image with no text |
| `STIMULUS_NO_QUESTIONS` | warning | a section with shared text and no questions |
| `CATEGORY_ORPHAN_PARENT` | warning | `parent_id` → nonexistent category |

One enum, one human-readable description per case, read by **both** the console and
`lab:import --help` (FR-044).
