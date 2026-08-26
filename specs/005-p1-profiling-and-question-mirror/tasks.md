---
description: "Task list for P1 — Production Profiling & Question Mirror"
---

# Tasks: P1 — Production Profiling & Question Mirror

**Input**: `spec.md` (63 FRs, 5 clarifications), `plan.md` (9 groups, 1 open question),
`data-model.md` (14 tables), `notes.md` (8 Phase 0 findings),
`contracts/profiling-results.md`

**Tests**: `php artisan test` for the derivation core, the validators, the guardrails, idempotency,
resume and reproducibility. `php artisan lab:health` is an executable test and this project's
acceptance instrument — **10/10, exit 0** after every phase, or the phase is not done. No coverage
target, no mocking framework, no UI wiring tests (constitution V).

**Format**: `[ID] [P?] [Story]` — `[P]` = parallelisable (different files, no incomplete
dependency) · US1 profiling · US2 the mirror · US3 the guarantees · US4 validation · US5 the console
· `[OPERATOR]` = needs a human decision or action.

---

## Shape

```text
Phase 1  Setup                                          2 tasks
Phase 2  Foundational — BLOCKING                        2 tasks   ⚠️ one is an open question
Phase 3  US1  Profiling            (P1)  🎯 MVP        11 tasks   ← can re-scope everything below
Phase 4  US2  The mirror           (P2)                50 tasks
Phase 5  US4  Validation           (P4)                 6 tasks   ← plugs into Phase 4's hook
Phase 6  US3  The guarantees       (P3)                 5 tasks
Phase 7  US5  The console          (P5)                12 tasks
Phase 8  Wrap-up                                        6 tasks
                                                       94 tasks
```

**Phase order is not story-priority order.** US3 (the guarantees) is P3 by value but most of it
cannot be asserted until the schema and the ETL exist, so it runs after US2 and US4. US4's checks
plug into an error-recording hook that US2 builds empty — which is what keeps both independently
testable.

---

## Phase 1: Setup

**Purpose**: the two pieces of shared configuration everything below reads.

- [X] T001 `apps/lab/config/lab.php` — add an `import` block (`chunk_size` default **10000**,
  `source_system` constant `'injazedu_production'`) and a `profiling` block (`sql_path` pointing at
  `sql/profiling`, `report_path` pointing at `docs/reports/p1-profiling.md`). Comment each with the
  FR it serves, matching the file's existing style (FR-022, FR-004)
- [X] T002 Create `docs/reports/` and confirm it is tracked by Git and **not** ignored — the
  profiling report is generated into it and must be diffable, since regenerating it identically is
  an acceptance criterion (FR-005, SC-001)

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ T003 blocks every migration in Phase 4.** Running `migrate` before it is settled turns the test
suite red for a reason that has nothing to do with the mirror being wrong.

- [X] T003 [OPERATOR] **Open Question 1 in `plan.md` must be answered first**, then rewrite
  `apps/lab/tests/Feature/NoPiiInLabSchemaTest.php`. The delivered test forbids the column name
  `name` on every non-framework table — which `source_categories`, `source_courses`,
  `source_quizzes` and `source_sections` all need — and does **not** check `user_id` at all, the one
  column FR-011 exists for (notes N2). Recommended shape: hard-forbid `user_id`, `email`, `phone`,
  `mobile`, `id_number`, `national_id`, `username`, `first_name`, `last_name`, `full_name` on every
  non-framework table; forbid `name` on **behavioural** tables only (`source_results`,
  `source_answers`), which is what the test's own docblock already says. Record the decision in the
  test's docblock (FR-011, FR-054, SC-009)
- [X] T004 [OPERATOR] Confirm **`STUDENT_REF_PEPPER`** is set in `apps/lab/.env` and stored outside
  Git **and off this machine**. This gates **T055–T059 only**, not the rest of the project: once
  ~1.1 M `student_ref` values exist, changing the pepper orphans every behavioural row and there is
  no backup by program decision (spec Dependencies, P1 plan §8 item B)

**Checkpoint**: with T003 answered, Phases 3 and 4 can proceed; T004 gates only the behavioural
import.

---

## Phase 3: US1 — The bank is measured before anything is built on it (Priority: P1) 🎯 MVP

**Goal**: eighteen queries run inside the three read-only layers, their results persist as
queryable data, and a report is generated from that data.

**Independent test**: `php artisan lab:profile --dry-run` lists eighteen files with their declared
tables and executes nothing; a full run puts all eighteen results in
`source_snapshots.profiling_results`; regenerating the report from that JSON produces an identical
file.

**Why this is the MVP**: it is the only phase that can ship alone and still leave the program better
off — and its output can re-scope everything below it (see T014).

- [X] T005 [US1] `apps/lab/database/migrations/2026_08_25_100000_create_source_snapshots_table.php`
  — per `data-model.md` §2: `snapshot_taken_at`, `loaded_at`, `mysql_version`,
  `source_database_size_mb`, `source_row_counts` JSONB, `profiling_results` JSONB,
  `profiling_report_path`, `notes`. **No common mirror columns** — this is a register, not a mirror
  (FR-006)
- [X] T006 [P] [US1] `apps/lab/app/Models/SourceSnapshot.php` — JSONB casts for
  `source_row_counts` and `profiling_results`, a scope for the latest run (FR-006)
- [X] T007 [US1] `apps/lab/app/Support/Profiling/QueryFile.php` — represents one `.sql` file:
  number, filename, title, declared tables, allowlist status, and **the executable statement with
  its leading `--` header stripped**. Guard 2 takes the first token of the statement, so passing the
  raw file contents to `DB::select()` throws `ReadOnlyViolation` on a pure `SELECT` (notes N1). A
  file with no parseable `-- Tables read :` header is a **hard failure**, never a default-to-empty
  (FR-002, notes N5)
- [X] T008 [US1] `apps/lab/app/Support/Profiling/QueryPack.php` — discovers `sql/profiling/*.sql` in
  numeric order and returns eighteen `QueryFile`s. Order is numeric, not lexical, so a future file 19
  does not sort between 1 and 2 (FR-001)
- [X] T009 [US1] `apps/lab/app/Console/Commands/LabProfile.php` — `lab:profile` with `--dry-run` and
  `--query=N`. **Every declared table name of every file passes `SourceReader::assertReadable()`
  before the first file executes**; one refusal stops the run and names the table. Execution is
  `DB::connection('injazedu')->select()`, so guards 1 and 2 apply automatically (FR-001, FR-002,
  FR-003)
- [X] T010 [US1] `LabProfile` — persist a **new** `source_snapshots` row per run holding the
  envelope defined in `contracts/profiling-results.md`: `schema_version`, `snapshot_taken_at`,
  `run_at`, `mysql_version`, `source_database_size_mb`, and `queries` keyed `"1"`…`"18"` with `rows`
  **verbatim** — no renaming, no rounding, no derived percentages. A failed query records `error`
  and no `rows` key; a partial run is still persisted (FR-004, FR-006, FR-008)
- [X] T011 [US1] `apps/lab/app/Support/Profiling/ReportGenerator.php` — generates
  `docs/reports/p1-profiling.md` **from the stored JSON alone**, with `snapshot_taken_at` at the top
  and each query under its number. Regenerating without a new run must produce a byte-identical
  file, so nothing in it may come from the run's in-memory state (FR-005, SC-001)
- [X] T012 [US1] `LabProfile` — the terminal table, in **English** (operator output, constitution
  VI), and the final line naming the snapshot row written (FR-004)
- [X] T013 [P] [US1] `apps/lab/tests/Feature/ProfileDeclarationTest.php` — the eighteen file headers
  match the table in `sql/profiling/README.md`; a fixture file declaring `users` makes the command
  fail **naming that table, before any SQL executes**; a file with no header fails; and `--dry-run`
  executes nothing (FR-002, FR-003, SC-002)
- [X] T014 [US1] **Run `php artisan lab:profile` in full.** Confirm against P0's baseline on the same
  snapshot: `questions` **29,142**, `options` **124,549**, `quizzes` **3,362**, `courses` **231**. A
  mismatch is a bug in the command, not drift in the bank — the snapshot is fixed at 2026-08-07
  (FR-006, SC-003). **⚠️ If query 3 puts `correct_count = 0` above 2%, stop here** and re-scope with
  the operator before Phase 4: the broken-question list becomes the program's first deliverable
  (plan "One Fork Worth Planning For", FR-063)
- [X] T015 [OPERATOR] [US1] Read the generated report and record the three findings that change code: the
  **meaning of multi-key** (queries 3+4 — gates T062), the **enrolment table** (queries 15+16), and
  the **broken-question rate** (query 3). Add the `**Updated**` note to §13 of
  `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` pointing at the report —
  the estimate stays visible beside the measurement (FR-060, FR-061, FR-062, SC-004, SC-021)

**Checkpoint**: US1 is complete and independently useful. Every number the rest of the program
quotes now exists as data.

---

## Phase 4: US2 — A faithful, complete, re-runnable mirror (Priority: P2)

**Goal**: fourteen tables, Production identifiers preserved, soft-deleted rows copied, `user_id`
replaced by `student_ref` at read time, idempotent and resumable.

**Independent test**: run `lab:import` twice against a small fixed set — the second run reports
`rows_inserted = 0`, `rows_updated = 0`, `error_count = 0`; kill it mid-batch, `--resume`, and find
neither a gap nor a duplicate.

### The schema (13 migrations — T003 must be answered first)

All follow `data-model.md`: the eight common columns, `UNIQUE (source_system, source_id)`, and a
comment stating **what was not copied and why**. `sorte_order → sort_order` is a **per-table**
mapping — `quizzes` spells it correctly and a blanket rename would silently NULL that column
(notes N4).

- [ ] T016 [P] [US2] `..._create_source_categories_table.php` — `parent_source_id` copied as-is from
  an **INT** `parent_id` against a **BIGINT UNSIGNED** `id` with no FK; expect orphans and cycles.
  Not copied: `meta_*`, `courses_card`, `quizzes_card`, `events_card`, `mobile_image` (FR-009, FR-015)
- [ ] T017 [P] [US2] `..._create_source_courses_table.php` — metadata only: name, slug, category,
  status, `start_date`, `exam_date`, the three Telegram fields. **Not copied**: `price`, `discount`,
  `description` (NOT NULL in the source — notes N7), `course_conditions`, `meta_*`, images (FR-012)
- [ ] T018 [P] [US2] `..._create_source_chapters_table.php` — `title`, `sort_order`, `course_source_id`
- [ ] T019 [P] [US2] `..._create_source_lectures_table.php` — `topic`, `sort_order`,
  `chapter_source_id`. **Not copied**: `zoom_start_url`, `zoom_join_url`, `meeting_id`, `passcode`,
  `meeting_type`, `vimeo_id`, `bunny_id`, `youtube_id`, `upload_*`, `host`, `live`, `book` — some are
  credentials, none is about a question (FR-012)
- [ ] T020 [P] [US2] `..._create_source_quizzes_table.php` — `course_source_id` NULL ⇒ general quiz.
  **`user_id` is not copied**; quiz-level attribution is lost and that is accepted (FR-012)
- [ ] T021 [P] [US2] `..._create_source_sections_table.php` — plus `stimulus_raw`,
  `stimulus_length`, `has_stimulus`, `is_long_stimulus`, `questions_count` (FR-013)
- [ ] T022 [P] [US2] `..._create_source_questions_table.php` — plus `correct_option_count`,
  `answer_key_state` **defaulting to `pending`**, `options_count`, `stem_char_length`, `has_html`,
  `has_img`, `is_stem_image_only`, `requires_media_review`, `source_origin` defaulting to `unknown`.
  Index on `section_source_id` (FR-014, FR-034)
- [ ] T023 [P] [US2] `..._create_source_question_options_table.php` — `source_order`, `option_index`,
  `is_correct_derived`. Index on `question_source_id` (FR-014, FR-017)
- [ ] T024 [P] [US2] `..._create_source_media_table.php` — `type`, `path` (nullable in the source),
  `attach_level`, `path_unverified`. Comment: `quiz_files` has **no soft delete**, so
  `source_deleted_at` here is permanently NULL (notes N3, FR-035)
- [ ] T025 [P] [US2] `..._create_source_results_table.php` — `student_ref CHAR(64)`, `attempt_index`,
  `duration_estimate_seconds`, `total_points`. Indexes on `quiz_source_id` and `student_ref`.
  **No `user_id` column** (FR-011, FR-014, FR-037)
- [ ] T026 [P] [US2] `..._create_source_answers_table.php` — `result_source_id`,
  `question_source_id`, `option_source_id`, `points`, `is_correct_derived`. Indexes on
  `question_source_id` and `result_source_id`. Comment: `question_result` has no soft delete, and
  `results` does — so excluding deleted attempts must go through `source_results` (notes N3)
- [ ] T027 [P] [US2] `..._create_import_runs_table.php` — `snapshot_id`, `kind`, `started_at`,
  `finished_at`, `status`, `rows_read/inserted/updated/unchanged`, `error_count`, `elapsed_seconds`,
  `resume_cursor` JSONB, `ran_via` (FR-028, FR-041)
- [ ] T028 [P] [US2] `..._create_import_errors_table.php` — `import_run_id`, `source_table`,
  `source_id`, `severity`, `code`, `message`, `context` JSONB. **Append-only, scoped by run, never
  deleted or rewritten** — state this in the migration comment, because it is the property the
  console's card design depends on (FR-027)
- [ ] T029 [US2] `apps/lab/app/Models/` — twelve `Source*` models plus `ImportRun` and `ImportError`,
  with casts for the JSONB and boolean columns and the relationships in `data-model.md`
- [ ] T030 [US2] Run `php artisan migrate:fresh`, then `php artisan test --filter=NoPii` — the
  rewritten assertion must pass over the complete fourteen-table schema (FR-054, SC-009)

### The derivation core (no database — test before it touches a row)

- [ ] T031 [P] [US2] `apps/lab/app/Support/Derive/AnswerKeyDeriver.php` — `correct_option_ids` from
  live options with `points > 0`; `correct_option_count` is mechanical and returned always;
  `answer_key_state` is an **interpretation** and is returned as `pending` until the policy from
  T015 is configured (FR-016)
- [ ] T032 [P] [US2] `apps/lab/app/Support/Derive/OptionIndexDeriver.php` —
  `` ORDER BY `order` ASC, id ASC ``, never abbreviated. `order` is a reserved word in MySQL:
  always backticks (FR-017)
- [ ] T033 [P] [US2] `apps/lab/app/Support/Derive/PayloadHasher.php` — SHA256 over key-sorted JSON.
  **One mechanism, one exception**: every table hashes its own copied columns; `source_questions`
  uses §16's definition verbatim — `name`, `description`, `hint`, options by `option_index` with
  `name` and `points` (FR-018)
- [ ] T034 [P] [US2] `apps/lab/app/Support/Derive/StudentRefHasher.php` — `HMAC-SHA256(pepper,
  user_id)`, reading the pepper from config. **Throws on an empty or missing pepper** (FR-019)
- [ ] T035 [P] [US2] `apps/lab/tests/Unit/Derive/AnswerKeyDeriverTest.php` — 1, 0 and >1 correct;
  soft-deleted options excluded; `pending` returned while no policy is configured (FR-021)
- [ ] T036 [P] [US2] `apps/lab/tests/Unit/Derive/OptionIndexDeriverTest.php` — **the `order` tie
  case is mandatory** (query 5): identical `order` values resolve by `id`, and the result is stable
  across repeated runs. This is the case that breaks everything silently (FR-021)
- [ ] T037 [P] [US2] `apps/lab/tests/Unit/Derive/PayloadHasherTest.php` — same input ⇒ same hash;
  re-ordering the input options does **not** change it; changing an option's text does; and a
  non-question table hashes its own columns (FR-018, FR-021)
- [ ] T038 [P] [US2] `apps/lab/tests/Unit/Derive/StudentRefHasherTest.php` — stable for the same
  input, different for a different pepper, and **throws on an empty pepper** (FR-019, FR-021)

### The ETL structure

- [ ] T039 [US2] `apps/lab/app/Support/Import/Upsert.php` — upsert on
  (`source_system`, `source_id`). Matching `payload_hash` ⇒ `rows_unchanged++` and **no write**;
  differing ⇒ update and `rows_updated++`; unseen ⇒ insert. **`SourceReader::assertCopyable($table)`
  is called here, at the single write site every job funnels through** (FR-023, FR-026)
- [ ] T040 [US2] `apps/lab/app/Support/Import/ResumeCursor.php` — records (table, last confirmed
  `source_id`) into `import_runs.resume_cursor`, updated **after each confirmed batch** — not per
  row, and never before the batch commits (FR-025)
- [ ] T041 [US2] `apps/lab/app/Support/Import/ImportErrorRecorder.php` — writes an anomaly with
  code, severity, table, source id, message and context, and **returns so the batch continues**. A
  silent `try/catch` is a defect. `user_id` must never reach `context`: hashing happens at read
  time, before any error path can see it (FR-020, FR-027)
- [ ] T042 [US2] `apps/lab/app/Support/Import/ImportRunRecorder.php` — creates the run row, links
  every written row via `import_run_id`, and records counts, `elapsed_seconds` and `ran_via` on
  completion (FR-022, FR-028, FR-041)
- [ ] T043 [US2] `apps/lab/app/Console/Commands/LabImport.php` — `lab:import` with `--kind`,
  `--resume`, `--chunk`, `--queue`. **Synchronous by default** with progress and a real exit code;
  `--queue` dispatches the *same* job classes to the `database` queue. Two implementations is a
  defect — only one of them would be the tested one (FR-022, FR-029)
- [ ] T044 [US2] `LabImport --help` — the operating instructions: what each flag does, what
  `--resume` picks up, and what each error code means, read from the enum. **This is the import's
  documentation; no runbook is written** (FR-030)

### Bank ETL — the order is mandatory (key dependencies, not preference)

Each job reads through `SourceReader`, derives with the Phase 4 core, and writes through T039's
upsert. **Soft-deleted rows are copied** with `source_deleted_at`; exclusion is an analysis-time
decision made somewhere else (FR-032).

- [ ] T045 [US2] `apps/lab/app/Jobs/Import/Bank/ImportCategories.php` — `parent_id` copied as-is,
  never repaired (FR-031, FR-033)
- [ ] T046 [US2] `apps/lab/app/Jobs/Import/Bank/ImportCourses.php` — permitted columns only; a
  review step confirms price and the marketing fields were not copied (FR-012)
- [ ] T047 [US2] `apps/lab/app/Jobs/Import/Bank/ImportChapters.php`
- [ ] T048 [US2] `apps/lab/app/Jobs/Import/Bank/ImportLectures.php`
- [ ] T049 [US2] `apps/lab/app/Jobs/Import/Bank/ImportQuizzes.php`
- [ ] T050 [US2] `apps/lab/app/Jobs/Import/Bank/ImportSections.php` — computes `stimulus_raw`,
  `stimulus_length`, `has_stimulus`, `is_long_stimulus`. **`sections.description` is absent from
  `$fillable` but the column exists and may be populated — read it, never assume it is empty**; it is
  the whole substance of §8 (FR-013)
- [ ] T051 [US2] `apps/lab/app/Jobs/Import/Bank/ImportQuestions.php` — `raw_text` unmodified, plus
  `has_html`, `has_img`, `is_stem_image_only`, `stem_char_length`, `options_count`,
  `correct_option_count` and `payload_hash`. **`answer_key_state` stays `pending`** (FR-034, FR-061)
- [ ] T052 [US2] `apps/lab/app/Jobs/Import/Bank/ImportQuestionOptions.php` — `option_index` and
  `is_correct_derived` (FR-017)
- [ ] T053 [US2] `apps/lab/app/Jobs/Import/Bank/ImportMedia.php` — both attachment levels,
  `path_unverified = true` on every row (FR-035)
- [ ] T054 [US2] Verify each table's copied count equals the count query 1–12 recorded in T014,
  **soft-deleted rows included**, and that every `source_id` is unchanged (FR-036, SC-005)

### Behavioural ETL — T004 must be confirmed first

- [ ] T055 [US2] `apps/lab/app/Jobs/Import/Behaviour/ImportResults.php` — ~1.1 M rows.
  **`user_id` is read, hashed and discarded in the same statement**: no intermediate variable holds
  it, no log prints it, no column receives it (FR-020, FR-037)
- [ ] T056 [US2] `apps/lab/app/Jobs/Import/Behaviour/DeriveAttemptIndex.php` — a second pass in
  **Postgres**: `ROW_NUMBER() OVER (PARTITION BY student_ref, quiz_source_id ORDER BY
  source_created_at)`. An order of magnitude cheaper than 1.1 M PHP iterations (FR-038)
- [ ] T057 [US2] `ImportResults` — `duration_estimate_seconds` from `updated_at − created_at`,
  labelled an approximation **in the column name itself** so it is never read as a real duration
  (FR-039)
- [ ] T058 [US2] `apps/lab/app/Jobs/Import/Behaviour/ImportAnswers.php` — ~13.8 M rows in `--chunk`
  batches (10,000 start), `resume_cursor` updated after each confirmed batch,
  `is_correct_derived = points > 0` (FR-040)
- [ ] T059 [US2] Verify `COUNT(source_answers)` equals the source count exactly with no gap, and
  record `elapsed_seconds` — a number P3 needs to size its own batches, **not a gate** (FR-041,
  SC-006)

### The backfill passes — one idempotent second-pass pattern, three uses

- [ ] T060 [US2] `apps/lab/app/Jobs/Import/BackfillQuestionsCount.php` — `source_sections.questions_count`,
  after `source_questions` exists (FR-013)
- [ ] T061 [US2] `apps/lab/app/Jobs/Import/BackfillRequiresMediaReview.php` —
  `source_questions.requires_media_review` from audio/video `source_media` rows, after
  `source_media` exists (FR-034)
- [ ] T062 [US2] `apps/lab/app/Jobs/Import/BackfillAnswerKeyState.php` — **gated on T015's multi-key
  decision**. Sets `answer_key_state` from `correct_option_count` under the recorded policy, touches
  no other column, and re-running changes nothing. No question may leave `pending` on a guess
  (FR-061, SC-020)

### Idempotency and resume

- [ ] T063 [P] [US2] `apps/lab/tests/Feature/ImportIdempotencyTest.php` — two consecutive runs
  against a fixed set: the second reports `rows_inserted = 0`, `rows_updated = 0`, `error_count = 0`,
  and `rows_unchanged` equals the mirror. Cover a bank table **and** a behavioural table (FR-024,
  SC-007)
- [ ] T064 [P] [US2] `apps/lab/tests/Feature/ImportResumeTest.php` — interrupt mid-batch, then
  `--resume`: continues from the cursor, final counts match, **no row exists twice and none is
  missing**. Test the interruption in practice, not in theory (FR-025, SC-008)
- [ ] T065 [P] [US2] `apps/lab/tests/Feature/ImportQueueParityTest.php` — the same input through
  the inline path and through `--queue` produces the same mirror, and both record `ran_via`
  (FR-029, SC-019)

**Checkpoint**: the mirror is complete, re-runnable and resumable. P3 already has everything it
needs from this project.

---

## Phase 5: US4 — Data problems visible and classified, never hidden or repaired (Priority: P4)

**Goal**: thirteen named checks write to `import_errors` with code and severity; the batch always
continues; the mirror's broken rate equals the profiling run's.

**Independent test**: import a fixture set holding one instance of each anomaly — thirteen codes
appear, the batch completed, and nothing was repaired or dropped.

- [ ] T066 [US4] `apps/lab/app/Support/Import/ImportErrorCode.php` — a **backed enum**, thirteen
  cases, each with a human-readable description. `ZERO_CORRECT` is `severity = error` — it affects a
  student now. **One source of truth**, read by both the console and `lab:import --help` (FR-042,
  FR-043, FR-044)
- [ ] T067 [P] [US4] `apps/lab/app/Support/Import/Validators/` — the question-level checks:
  `MISSING_OPTIONS`, `EMPTY_STEM`, `ZERO_CORRECT`, `MULTI_CORRECT`, `STEM_IMAGE_ONLY`,
  `QUESTION_NO_SECTION`, `ORPHAN_SECTION` (FR-042)
- [ ] T068 [P] [US4] `apps/lab/app/Support/Import/Validators/` — the option- and structure-level
  checks: `DUPLICATE_OPTION_TEXT`, `OPTION_ORDER_TIE` (**options only** — `sections.order` and
  `questions.order` default to 1 and their ties are not defects, notes N6), `ORPHAN_QUIZ`,
  `CATEGORY_ORPHAN_PARENT`, `STIMULUS_NO_QUESTIONS` (FR-042)
- [ ] T069 [P] [US4] `apps/lab/app/Support/Import/Validators/BrokenHtmlValidator.php` —
  `BROKEN_HTML` **must not stop the batch**: isolated, logged, run continues. The row is still copied
  faithfully (FR-043, FR-046)
- [ ] T070 [US4] Wire the validators into the bank jobs through T041's recorder. **No check may
  repair, normalize, or drop a row** — anomalies are recorded beside faithful copies (FR-046)
- [ ] T071 [P] [US4] `apps/lab/tests/Unit/Validators/` — one test per code, thirteen in all, each
  proving the anomaly is detected **and** the row still copied. Plus: the `ZERO_CORRECT` rate among
  active questions equals query 3's result from T014 — a discrepancy means one of the two is wrong
  and blocks acceptance (FR-045, SC-013, SC-014)

**Checkpoint**: every anomaly the bank contains is visible, classified and attributable to a run.

---

## Phase 6: US3 — The boundary is a tested property, not a promise (Priority: P3)

**Goal**: every guarantee this project makes about data can be demonstrated by running the suite.

**Independent test**: run the suite on a populated mirror; then delete one `assertCopyable()` call
and confirm the suite goes red.

- [ ] T072 [P] [US3] `apps/lab/tests/Feature/CopyGuardTest.php` — every ETL write site passes
  through `assertCopyable()`; **removing it from any one site fails this test**; copying a
  `profile_tables` table throws **by name** while reading a count from it still succeeds. `orders`
  is the sharpest case: legitimately readable for query 15, and a serious leak if ever copyable
  (notes N7, FR-026, FR-055, SC-010, SC-011)
- [ ] T073 [P] [US3] `apps/lab/tests/Feature/NoUserIdAnywhereTest.php` — no column, no
  `import_errors.context` payload, and no log line written by the ETL contains a `user_id`. The
  column assertion cannot see a JSONB payload, which is exactly where a leak would hide (FR-020,
  SC-009)
- [ ] T074 [P] [US3] `apps/lab/tests/Feature/StatisticReproducibilityTest.php` — take one number
  the console displays and recompute it from the raw mirror rows; the two must agree (FR-057,
  SC-016)
- [ ] T075 [US3] Re-run `apps/lab/tests/Feature/ReadOnlyGuardTest.php`,
  `SourceTableAllowlistTest.php` and `ForbiddenTableRefusalTest.php` **unmodified**. Each of the
  three write-blocking layers must still refuse alone. If any needed a change to pass, that is a
  finding, not a fix (FR-056, SC-012)
- [ ] T076 [US3] `php artisan lab:health` — **10/10, exit 0**. Run it at the end of every phase, not
  only here; it is the instrument, and 7.058 s cold is the baseline (FR-058, SC-017)

**Checkpoint**: the non-negotiables in spec §11 are tested properties. None of them is accepted at
any other value.

---

## Phase 7: US5 — The team sees the bank instead of reading about it (Priority: P5)

**Goal**: an Arabic RTL console where every number is a link and every screen carries
`snapshot_taken_at`.

**Independent test**: open the console on a populated mirror and reach the underlying questions from
every displayed count in one click, with the snapshot date visible on every screen.

- [ ] T077 [US5] `apps/lab/app/Providers/Filament/AdminPanelProvider.php` — the **existing** panel
  becomes Arabic and RTL globally. **No second panel.** P0's health page keeps its English check
  names and values inside the RTL shell — technical identifiers and operator output stay English
  (FR-047)
- [ ] T078 [P] [US5] `apps/lab/lang/ar/` — the console's Arabic strings. Technical identifiers
  (`payload_hash`, `answer_key_state`, the thirteen codes) stay English (FR-047)
- [ ] T079 [US5] `apps/lab/app/Filament/Widgets/SnapshotHeader.php` — a fixed header on **every**
  screen: `snapshot_taken_at`, the row count, and the date of the last import run. No number is
  displayed without its frame (FR-048, SC-015)
- [ ] T080 [P] [US5] `apps/lab/app/Filament/Resources/SourceQuestionResource.php` — the list, the
  filters, and the single-question view with its **ordered** options and derived correct answer.
  A/B/C/D letters are synthesized from `option_index` at render time and never stored (FR-050)
- [ ] T081 [P] [US5] `apps/lab/app/Filament/Resources/SourceSectionResource.php` — sections with
  shared text, `stimulus_length` and `is_long_stimulus` (§8's basis)
- [ ] T082 [P] [US5] `apps/lab/app/Filament/Resources/SourceQuizResource.php` and
  `SourceCourseResource.php` — the navigation from course and quiz down to questions
- [ ] T083 [US5] `apps/lab/app/Filament/Pages/Inventory.php` — the cards: total questions
  (active / soft-deleted), by category, by course, by quiz, the option-count distribution,
  answer-key integrity, no-explanation, HTML, images, shared-text sections, media review.
  **All sourced from the mirror's own columns — never from `import_errors`**, so a no-op re-import
  cannot make a problem appear fixed (FR-049)
- [ ] T084 [US5] `Inventory` — **every number is a link**: number → filtered list → the question
  itself. This is a constitution VI requirement, not a nicety (FR-050, SC-015)
- [ ] T085 [P] [US5] `apps/lab/app/Filament/Resources/ImportErrorResource.php` — filterable by code,
  severity and source table. **Names the import run it is displaying** and defaults to the latest run
  that actually wrote rows, with earlier runs reachable — the log is history and history is not
  overwritten (FR-051)
- [ ] T086 [US5] `apps/lab/app/Support/Suppression.php` + apply it wherever a group count renders:
  `n < 10` publishes nothing, `n < 30` partially, `n ≥ 30` fully. Pinned here as a pattern before
  P3's numbers exist (FR-052)
- [ ] T087 [US5] State the two limits the console cannot resolve, on the screens where they matter:
  media paths are **unverified**, and a missing answer row cannot distinguish "not answered" from
  "not shown" (`question_result.option_id` is NOT NULL). What cannot be known is not presented as
  known (FR-053)
- [ ] T088 [US5] Confirm P0's health page still reports 10/10 inside the now-RTL panel — the
  direction changed, the operator output did not (FR-047, SC-017)

**Checkpoint**: the mirror is visible, navigable, and honest about what it cannot say.

---

## Phase 8: Wrap-up

**The only documents this project produces**, each because something outside the code needs it.

- [ ] T089 [P] `README.md` — a P1 section: the two commands and the one screen. Following it from a
  clean Lab database must reach a populated inventory console (FR-059, SC-018)
- [ ] T090 [P] `apps/lab/.env.example` — any new key, listed with **no value** (FR-059)
- [ ] T091 [P] `docs/runbooks/snapshot.md` — record the fixed-copy decision **once**: the snapshot is
  2026-08-07, there is no refresh, and no gate anywhere blocks on its age (FR-060)
- [ ] T092 `CLAUDE.md` and `AGENTS.md` — P1's measured facts, **byte-identical in both**. Verify with
  `diff CLAUDE.md AGENTS.md` (FR-060)
- [ ] T093 Final acceptance run: `php artisan test`, `php artisan lab:health` (10/10, exit 0), and
  the twenty-one success criteria in `spec.md` each confirmed or explicitly recorded as not met with
  a reason. **Assert zero rows written to `injazedu`** and zero rows from `profile_tables` stored
  (SC-012, SC-017, SC-021)
- [ ] T094 Confirm **no new runbook, ADR, acceptance record, or handover document** was created —
  and that `docs/reports/p1-profiling.md` is generated, not hand-edited. If a document was written,
  it needs a reason beyond "the process asks for one" (FR-060, SC-021)

---

## Dependencies

```text
Phase 1 ─► Phase 2 ─┬─► Phase 3 (US1) ──────────────┐
                    │        │                       │
                    │        └─ T015 decision ───────┼──► T062 (answer_key_state backfill)
                    │                                │
                    └─► Phase 4 (US2) ──► Phase 5 (US4) ──► Phase 6 (US3) ──► Phase 8
                              │                │
                              └────────────────┴──► Phase 7 (US5)
```

- **T003 blocks every migration.** It is an open question, not a task to start on.
- **T004 blocks T055–T059 only.** Everything else can be built while the pepper is being confirmed.
- **Phase 3 and Phase 4's schema/core work are independent** and can run side by side — but **T054
  needs T014's counts**, and **T062 needs T015's decision**.
- **Phase 5 plugs into Phase 4**: the validators fill a recorder hook that T041 builds empty. That is
  what keeps the two independently testable.
- **Phase 6 needs a populated mirror** — most of its assertions cannot exist before Phase 4.
- **Phase 7 needs Phase 4** for data and **Phase 5** for the error codes on its errors screen.
- **The bank order T045 → T053 is mandatory** — key dependencies, not preference.

### Parallel opportunities

```text
T016 ‖ … ‖ T028      thirteen migrations, one file each
T031 ‖ … ‖ T038      four derivations and their four unit tests
T067 ‖ T068 ‖ T069   validators in separate files
T072 ‖ T073 ‖ T074   three test files, no shared state
T080 ‖ T081 ‖ T082   Filament resources, one file each
T089 ‖ T090 ‖ T091   three documents, no shared state
Phase 3 ‖ Phase 4's T016–T038   profiling does not block the schema or the core
```

---

## What is deliberately NOT here

```text
Arabic normalization · clean_text · search_text     ← P2
Any similarity hash, embedding, vector, neighbour   ← P2
Any duplicate detection, clustering, or judgment    ← P2
Any item statistic (p-value, discrimination)        ← P3
Any classification, taxonomy, or coverage map       ← P5
Any LLM call whatsoever                             ← P2 and beyond
Any vector or trigram index                         ← earned, not assumed
Refreshing the snapshot                             ← cancelled; fixed at 2026-08-07
Any backup, dump, or restore                        ← cancelled program-wide
Any gate on a memory number or the snapshot's age   ← cancelled
Correcting any data in the source                   ← the source is read, never corrected
A new runbook, ADR, acceptance record, or handover  ← lab:import --help is the documentation
```

**The carried-over rule**: if a task starts comparing two questions to each other, it belongs to P2.

If a task here starts to settle a question the operator has not settled, it is not a task — it is an
open question for `plan.md` (Principle I).

---

## Summary

| Phase | Story | Tasks | Delivers |
|---|---|---:|---|
| 1 | — | 2 | Configuration and the report directory |
| 2 | — | 2 | The no-PII rewrite (open question) and the pepper confirmation |
| 3 | US1 (P1) 🎯 | 11 | Eighteen queries run and persisted; the three findings answered |
| 4 | US2 (P2) | 50 | Fourteen tables, the derivation core, idempotent and resumable ETL |
| 5 | US4 (P4) | 6 | Thirteen codes, the batch never stopping, profiling ↔ mirror agreement |
| 6 | US3 (P3) | 5 | The guarantees as tests, including the removed-guard failure |
| 7 | US5 (P5) | 12 | The Arabic console, every number a link |
| 8 | — | 6 | README, env keys, the §13 note, the acceptance run |
| | | **94** | |

**Three tasks need a human**: **T003** (Open Question 1 — a security assertion), **T004** (the pepper
stored off this machine), and **T015** (the three findings, one of which needs a trainer).

**MVP**: Phase 3. Eleven tasks, and the program stops building on unmeasured numbers — which is the
principle the whole project was reordered around. It is also the phase that can send everything
after it back to the drawing board, which is why it goes first.
