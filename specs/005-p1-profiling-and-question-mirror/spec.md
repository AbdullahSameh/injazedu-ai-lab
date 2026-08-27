# Feature Specification: P1 — Production Profiling & Question Mirror

**Branch**: `p1/profiling-and-mirror-schema` · **Created**: 2026-08-25 · **Status**: Draft
**Implements**: `docs/plans/project/1/p1-production-profiling-and-question-mirror.md` (v2.0, leaned
2026-08-25) — all ten phases, as **one** spec (§7.1). Governed by §16 of
`docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0).
**Predecessor**: `specs/004-handover-and-p1-readiness` — two allowlists, the eighteen §6 query files
written and unexecuted, one command that starts the stack, `lab:health` 10/10.
**Contract in force**: `specs/004-handover-and-p1-readiness/contracts/source-access-and-stack.md` —
this feature's ETL is the **second party**: it calls `assertCopyable()` before every write.

> **Where a plan and the measured schema disagree, the schema wins** (constitution). Every column
> named below is taken from `docs/schema/injazedu-db-schema.md`, not from assumption.

---

## Scope

One sentence: **measure the bank, copy it faithfully, and make what was copied visible — without a
single PII column and without one row written back to the source.**

| Piece | Outcome |
|---|---|
| `php artisan lab:profile` | The eighteen §6 queries run **inside the three read-only layers**, and their results are persisted as data, not pasted into prose. |
| The measurement as a record | `source_snapshots.profiling_results` (JSONB) is authoritative; `docs/reports/p1-profiling.md` is **generated** from it and never hand-maintained. |
| Fifteen Lab tables | The question bank and the behavioural history, in Lab Postgres, with Production identifiers preserved and no `user_id` anywhere. Answer events are held as statistics, not rows (ADR-022). |
| A tested derivation core | Correct-answer state, option index, payload hash, student ref — proven before they touch a row. |
| `php artisan lab:import` | Idempotent, chunked, resumable. Re-running against the fixed snapshot changes nothing. |
| Thirteen validation checks | Every anomaly lands in `import_errors` with a code and a severity. Nothing is repaired, nothing is swallowed. |
| An Arabic inventory console | Every number clickable through to the questions, every screen carrying `snapshot_taken_at`. |

**P1 is a mirror, not a processor.** It measures, it copies faithfully, and it makes the copy
visible. Every meaningful transformation belongs to P2 and beyond.

**The carried-over rule:** *if you find yourself writing logic that compares two questions to each
other, you are in the wrong project.*

### Out of scope — writing any of this here is a defect

```text
Refreshing the snapshot                          ← cancelled; fixed at 2026-08-07
Arabic normalization · clean_text · search_text  ← P2
Any similarity hash, embedding, vector, or
  neighbour search                               ← P2
Any duplicate detection, clustering, or judgment ← P2
Any item statistic (p-value, discrimination,
  distractor analysis)                           ← P3
Any classification, taxonomy, or coverage map    ← P5
Any LLM call whatsoever                          ← P2 and beyond
Any vector index (HNSW) or trgm index            ← earned, not assumed
Any write by the Lab to MySQL or to the local
  snapshot                                       ← forbidden program-wide
Any connection to injazedu.co                    ← forbidden program-wide (§3.1)
Any backup, dump schedule, or restore drill      ← cancelled program-wide (§14.6)
Any gate or criterion on a memory number         ← cancelled (constitution VII)
Any gate on the snapshot's age                   ← cancelled (constitution III)
Correcting any data in the source                ← the source is read, never corrected
A new runbook, ADR, acceptance record, or
  handover document                              ← cancelled (constitution, doc policy)
```

**A note on correction.** If profiling reveals broken questions, this feature's output is a **list**
handed to the team to fix in the Production admin console. The Lab does not correct, and does not
propose automatic corrections, in this project.

---

## What This Feature Inherits

| Needed here | State on arrival |
|---|---|
| Three write-blocking layers, each blocking alone | Delivered (002), unchanged by the allowlist split |
| `SourceReader` with `assertReadable()` / `assertCopyable()` as **separate** methods | Delivered (002/004) — the union is never a copy check |
| `source_tables` (11 copyable) · `profile_tables` (6 read-only) · 15 refused by name | Delivered (004) |
| PostgreSQL 17 + pgvector + pg_trgm on 5433 | Delivered (001) — ready for the fifteen tables |
| Laravel 13 + Filament 5 on PHP 8.4; Laravel owns every migration (ADR-013) | Delivered (001) |
| `database` queue driver (ADR-011) | Delivered (001) — ready for ETL batches |
| `sql/profiling/` — eighteen numbered files, each declaring the tables it reads | Delivered (004), **never executed** |
| `STUDENT_REF_PEPPER` · `SNAPSHOT_TAKEN_AT` · `EMBEDDING_CONFIG_VERSION` in `.env` | Delivered (001–003) |
| `php artisan lab:health` — 10 checks, exit 0, 7.058 s cold | Delivered (003) — **the instrument every phase is measured by** |
| `NoPiiInLabSchemaTest` · `ReadOnlyGuardTest` · `SourceTableAllowlistTest` · `ForbiddenTableRefusalTest` | Delivered (002–004) — extended here, never weakened |

**The snapshot is fixed at 2026-08-07** and is the source for the entire local program. P0's counts
are therefore a **baseline to confirm**, not prior numbers to replace — a mismatch against the same
snapshot means a bug in `lab:profile`, not drift in the bank.

---

## The Security Property, Stated Once

Two paths leave the source, and the difference between them is the property this feature must not
lose:

```text
        assertCopyable(table)                 assertReadable(table)
        source_tables only (11)               source_tables ∪ profile_tables (17)
                 │                                      │
                 ▼                                      ▼
        rows stored in the Lab                counts returned, nothing stored
        ── the ETL                            ── lab:profile
```

The union of the two lists is **never** used as a copy check. `user_id` on `results` and
`question_result` is read, hashed, and discarded in the same statement — no intermediate variable
holds it, no log prints it, no column receives it.

The backstop is the **schema**, not the query: `NoPiiInLabSchemaTest` asserts that no column in the
Lab database is capable of holding personal data. That assertion is indifferent to what any passing
query happened to select, which is why it stays valid however far the read list grows.

---

## Clarifications

### Session 2026-08-25

- Q: What does `payload_hash` cover on the thirteen tables that are not `source_questions`? → A: Uniform — every table hashes its own copied source columns (key-sorted JSON), with `source_questions` the single exception, using §16's definition verbatim so an option edit changes the question's hash.
- Q: What happens to `import_errors` on a re-run that writes nothing? → A: Append-only per-run log scoped by `import_run_id`, never deleted; the errors screen names the run it shows and defaults to the latest run that wrote rows, while the integrity/quality cards read the mirror's own columns so a no-op re-run cannot make problems vanish.
- Q: Does the whole bank import wait on the multi-key decision? → A: No — the copy pass runs and derives everything except `answer_key_state`, which stays pending and is set by a separate idempotent backfill once the decision is recorded. `correct_option_count` is derived in the copy pass, since counting is not interpreting.
- Q: Does `lab:import` run inline or dispatch to the queue? → A: Synchronous by default — real progress and a real exit code — with `--queue` to dispatch instead for the ~13.8 M row behavioural run. Both paths share the same job classes, cursor and upsert.
- Q: Where does the Arabic RTL console live relative to P0's existing Filament panel? → A: One panel, Arabic and RTL globally; the existing health screen keeps its English technical output unchanged inside the RTL shell, since check names and values are technical identifiers.

### Session 2026-08-26

- Q: `results.user_id` is NULL on 71% of rows, and `source_results.student_ref` was NOT NULL. What should those rows carry? → A: `student_ref` is nullable and stays NULL. There is no id to hash, and a sentinel would falsely correlate thousands of unrelated anonymous attempts as one identity. `DeriveAttemptIndex` excludes them, so they keep a permanent NULL `attempt_index` — "this student's Nth attempt" is undefined without a student.
- Q: Should the 13.8M answer events be mirrored row-for-row? → A: No (ADR-022). They are unbounded behavioural data that nothing annotates individually, so they are aggregated by pushdown into `source_item_stats` and `source_option_stats`, and `question_result` moves to `profile_tables` so the copy guard refuses it by name. Attempts (`source_results`) stay mirrored.
- Q: Should the statistics count soft-deleted attempts? → A: Both, as a `scope` row discriminator (`active` and `all`). 71% of attempts are soft-deleted, so the two bases differ substantially and the choice belongs to P3 with real numbers on both sides.
- Q: Does P1 compute `r_pbis`? → A: No. P1 stores its inputs — `n`, `n_correct`, `p_value`, and the corrected-total `m1`/`m0`/`sd` — because those are what require the (attempt × question) grain. The coefficient itself is P3's, derivable from the stored columns without raw rows.

---

## User Scenarios & Testing

### US1 — The bank is measured before anything is built on it (Priority: P1)

The operator runs one command. Eighteen queries execute against the fixed snapshot **through the
guarded connection**, their full results are persisted as queryable data, and a report is generated
from that data. Three of the eighteen results are then read as decisions that change what gets built.

**Why this priority**: it is the governing principle of the whole project — *we do not build on an
unmeasured number*. P0 proved the assumption wrong by 16.6% on the very first number actually
measured. Every capacity estimate in §13 of the program plan, and the scope of P2, P5 and P9, wait on
this output. It is also the only phase that can be delivered alone and still leave the program better
off than it was.

**Independent test**: run `lab:profile --dry-run` and see eighteen files with their declared tables
and nothing executed; run it in full and find all eighteen results in `source_snapshots.profiling_results`
and a report regenerated identically from that JSON.

**Acceptance Scenarios**:

1. **Given** the eighteen query files, **When** `lab:profile --dry-run` runs, **Then** it lists all
   eighteen with the tables each declares, and executes no SQL.
2. **Given** a query file that declares `users`, **When** `lab:profile` runs, **Then** the command
   **fails naming that table**, before any SQL from any file is executed.
3. **Given** a full run, **When** it completes, **Then** one `source_snapshots` row holds all
   eighteen results as JSON, with `snapshot_taken_at`, the MySQL version, the source database size,
   and the row count of each of the eleven copyable tables.
4. **Given** that stored JSON, **When** the report is regenerated without re-running any query,
   **Then** the file produced is identical — no prose in it is hand-maintained.
5. **Given** the run, **When** its counts are compared with P0's baseline, **Then** `questions`
   = 29,142, `options` = 124,549, `quizzes` = 3,362 and `courses` = 231 — and any mismatch is
   reported as a defect in the command, not as bank drift.
6. **Given** `lab:profile --query=N`, **When** it runs, **Then** exactly that one query executes and
   its result is recorded.
7. **Given** a second full run, **When** it completes, **Then** it produces a **new** snapshot row —
   a comparison against the previous run, never an overwrite.
8. **Given** the whole run, **When** the source is inspected, **Then** zero rows were written to
   `injazedu`, and no row from any `profile_tables` table was stored anywhere in the Lab.

---

### US2 — A faithful, complete, re-runnable mirror (Priority: P2)

The whole question bank and the whole behavioural history are brought into Lab Postgres: fifteen
tables, Production identifiers preserved exactly, soft-deleted rows copied rather than dropped, and
`user_id` replaced by `student_ref` at the moment of reading. Running the import a second time
against the fixed snapshot changes nothing. Killing it halfway and resuming loses no row and
duplicates none.

**Why this priority**: it is what P2, P3, P4, P5 and P9 all consume. Idempotency and resume are not
conveniences — a 13.8 million row copy that cannot resume is a copy that gets abandoned, and an
import that is not idempotent cannot be trusted to have run once.

**Independent test**: run `lab:import` twice against a small fixed set and see `rows_inserted = 0`,
`rows_updated = 0`, `error_count = 0` on the second run; kill it mid-batch, `--resume`, and find
neither a gap nor a duplicate.

**Acceptance Scenarios**:

1. **Given** a clean Lab database, **When** the migrations run, **Then** every mirror table
   carries the common columns and a UNIQUE constraint on (`source_system`, `source_id`), and no
   table carries a `user_id`, email, phone, name, or national-id column.
2. **Given** the bank import, **When** it completes, **Then** the copied row count of each table
   equals the count query 1–12 recorded for it, **soft-deleted rows included**, and every
   `source_id` is the Production identifier unchanged.
3. **Given** a question whose options share the same `order` value, **When** its options are
   imported, **Then** `option_index` is assigned by `` ORDER BY `order` ASC, id ASC `` and is stable
   across runs — and the tie is recorded as `OPTION_ORDER_TIE`.
4. **Given** the same question imported twice, **When** the payload hashes are compared, **Then**
   they are identical; **When** an option's text changes, **Then** the hash changes; **When** only
   the input order of the options changes, **Then** the hash does not.
5. **Given** an empty `STUDENT_REF_PEPPER`, **When** a `student_ref` is derived, **Then** the
   derivation **throws** — a student ref built on an empty pepper is never produced.
6. **Given** the behavioural import, **When** it completes, **Then** `source_results` matches the
   source count exactly, the derived statistics reconcile to the full source answer count,
   `attempt_index` is derived per (`student_ref`, `quiz_source_id`) by creation order, and no column,
   log line, or error context anywhere contains a `user_id`.
7. **Given** a completed import, **When** `lab:import` runs again unchanged, **Then**
   `rows_inserted = 0`, `rows_updated = 0`, `error_count = 0`, and `rows_unchanged` equals the full
   mirror.
8. **Given** an import killed mid-batch, **When** `lab:import --resume` runs, **Then** it continues
   from the last confirmed cursor, the final counts match the source, and no row exists twice.
9. **Given** any ETL write site, **When** `assertCopyable()` is removed from it, **Then** a test
   fails.
10. **Given** a completed run, **When** its `import_runs` row is read, **Then** it carries the rows
    read, inserted, updated and unchanged, the error count, the elapsed time, the resume cursor, and
    the snapshot it was checked against — whether the run went inline or through `--queue`.
11. **Given** the multi-key decision is not yet recorded, **When** the bank import runs, **Then** it
    completes with every other column derived and `answer_key_state` **pending**; **When** the
    decision is recorded and the backfill pass runs, **Then** every active question leaves pending,
    no other column is touched, and re-running the backfill changes nothing.

---

### US3 — The boundary is a tested property, not a promise (Priority: P3)

Every guarantee this feature makes about data can be demonstrated by running the test suite: no PII
column exists, no profile-only table can be copied, the source is never written to, the import is
idempotent and resumable, and at least one number shown on the console can be reproduced from the
raw rows.

**Why this priority**: these are the program's non-negotiables (§11 second block) — none of them is
accepted at any value. They are placed third only because most of them cannot be asserted until the
schema and the ETL exist; they gate acceptance regardless of order.

**Independent test**: run the suite on a populated mirror. Then delete one `assertCopyable()` call
and confirm the suite goes red.

**Acceptance Scenarios**:

1. **Given** all fifteen tables populated, **When** the no-PII schema assertion runs, **Then** it
   passes over the complete schema, examining columns rather than query results.
2. **Given** any of the six profile-only tables, **When** the ETL is asked to copy it, **Then** it
   is refused **by name**, and reading a count from it still succeeds.
3. **Given** each of the three write-blocking layers, **When** one is disabled in turn, **Then** the
   remaining layers still refuse a write to `injazedu`.
4. **Given** a number displayed on the console, **When** it is recomputed from the raw mirror rows in
   a test, **Then** the two agree.
5. **Given** the whole feature, **When** `lab:health` runs, **Then** it reports 10/10 with exit 0 —
   after every phase, not only at the end.

---

### US4 — Data problems are visible and classified, never hidden or repaired (Priority: P4)

Thirteen named checks run during the import. Each anomaly is written to `import_errors` with a code,
a severity, the source table, the source id, and enough context to act on. The batch continues. The
broken-question rate the mirror reports equals the rate the profiling run measured.

**Why this priority**: a mirror that silently drops what it could not parse is worse than no mirror,
because it looks complete. And the rate agreement between profiling and mirror is the one check that
can catch a systematic ETL error that every count-based check would pass.

**Independent test**: import a fixture set containing one instance of each of the thirteen anomalies
and find thirteen codes in `import_errors`, the batch completed, and nothing repaired.

**Acceptance Scenarios**:

1. **Given** a question with unbalanced HTML, **When** it is imported, **Then** `BROKEN_HTML` is
   recorded, the row is still copied faithfully, and the batch does not stop.
2. **Given** a question whose options all carry `points = 0`, **When** it is imported, **Then**
   `answer_key_state = broken_no_key`, `ZERO_CORRECT` is recorded with `severity = error`, and the
   question is copied — never dropped and never corrected.
3. **Given** a category whose `parent_id` names a category that does not exist, **When** it is
   imported, **Then** `parent_source_id` is copied as-is and `CATEGORY_ORPHAN_PARENT` is recorded —
   the tree is shown incomplete and honest, not complete and guessed.
4. **Given** the completed import, **When** the `ZERO_CORRECT` rate among active questions is
   compared with query 3's result, **Then** the two agree — and a discrepancy is treated as a defect
   in one of them, not as an acceptable variance.
5. **Given** the thirteen codes, **When** a code's meaning is looked up, **Then** the console and
   `lab:import --help` give the same description, from one source of truth.

---

### US5 — The team sees the bank instead of reading about it (Priority: P5)

An Arabic, right-to-left console shows what the mirror holds: totals, distributions, integrity,
problems. Every number on it is a link — from the number, to the filtered list, to the question with
its options and its derived answer. Every screen carries `snapshot_taken_at` beside its numbers.

**Why this priority**: it is what turns the mirror from a database into a thing the team can act on,
and it is the deliverable §16 names last because it depends on everything else.

**Independent test**: open the console on a populated mirror and reach the underlying questions from
every displayed count in one click, with the snapshot date visible on every screen.

**Acceptance Scenarios**:

1. **Given** any card on the console, **When** its number is clicked, **Then** the filtered question
   list that produced it opens, and from there an individual question with its ordered options and
   its derived correct answer.
2. **Given** any screen, **When** it renders, **Then** `snapshot_taken_at`, the row count, and the
   date of the last import run appear in a fixed header — **no number is displayed without its
   frame**.
3. **Given** the errors screen, **When** it is filtered, **Then** it filters by code, by severity,
   and by source table.
4. **Given** a group whose count is below 10, **When** it would be displayed, **Then** nothing is
   published for it; below 30, partial only — the suppression rule is in place before P3's numbers
   exist.
5. **Given** a media row, **When** it is displayed, **Then** it states that the path is unverified;
   and **Given** any screen counting answers per question, **When** it renders, **Then** it states
   that a missing answer row cannot distinguish "not answered" from "not shown" — what cannot be
   known is not presented as known.
6. **Given** reviewer-facing text, **When** it renders, **Then** it is Arabic with correct RTL, while
   technical identifiers stay English.
7. **Given** P0's health screen in the now-RTL panel, **When** it is opened, **Then** its check names
   and values are still English and still pass 10/10 — the direction changed, the operator output
   did not.

---

### Edge Cases Worth Naming

- **The union used as a copy check.** One convenience method that checks `assertReadable()` before an
  insert would silently undo the entire allowlist split. Copy is its own method, at every write site,
  and the schema assertion is the backstop.
- **`user_id` surviving in a log or an error context.** `import_errors.context` is JSONB and will be
  filled by code written under pressure. A `user_id` in an error payload is a PII leak that no column
  assertion catches — the hashing happens at read time, before any error path can see the raw value.
- **The pepper changing after Phase 7.** ~1.1 million `student_ref` values become permanently
  unlinkable, with no backup to restore from. Prevention is the only cure; the derivation refuses an
  empty pepper, and the pepper's safe storage is a blocking human item.
- **`order` ties treated as noise.** `options.order` defaults to 0 and repeats. Unstable ordering
  changes "the second option" between runs, which corrupts `payload_hash`, human review, and P2's
  prompts. The two-key sort is mandatory everywhere and never abbreviated.
- **`sections.description` assumed empty because it is not in `$fillable`.** The column exists and
  may well be populated — it is the entire substance of the passage-based work in §8. It is read, and
  never assumed empty.
- **"A question with no answer row" read as a skip.** `question_result.option_id` is NOT NULL, so a
  skipped question produces **no row at all**. Not answered and not shown cannot be told apart. This
  goes into the report and onto the console, because P3 will be asked about it.
- **Soft-deleted rows filtered at copy time.** Excluding them at copy and excluding them at analysis
  are two different decisions in two different places. P1 copies them, with `source_deleted_at`.
- **`requires_media_review` computed before the media exist.** The mandatory import order puts
  `quiz_files` after `questions`, so a flag derived from media cannot be set in the questions pass.
- **A category cycle.** `categories.parent_id` has no FK and a type mismatch, so a cycle is possible.
  A tree walk that assumes acyclicity will hang. Cycles are logged, not repaired.
- **`lab:profile` widening its own reach.** A new query file that reads a forbidden table must fail
  **before** any SQL executes — which requires the declaration to be checked up front, for all
  files, not lazily per file.
- **A batch that "handles" an anomaly by skipping the row.** A silent `try/catch` is a defect. The
  row is copied faithfully and the anomaly is recorded beside it.
- **Resume that restarts.** A cursor updated before the batch is confirmed loses rows; a cursor
  updated per row costs more than the copy. It is updated after each confirmed batch, and the
  interruption is tested in practice, not in theory.

---

## Requirements

### The profiling command

- **FR-001**: The system MUST provide `php artisan lab:profile`, which executes the eighteen §6 query
  files in numeric order **over the guarded `injazedu` connection**. A direct database client MUST
  NOT be used, and no path may bypass the three read-only layers.
- **FR-002**: Each query file MUST carry a declaration of the tables it reads, taken from the table
  already present in `sql/profiling/README.md`. Every declared name MUST pass through
  `assertReadable()` **before any file executes**; a single refusal MUST stop the whole command and
  name the refused table.
- **FR-003**: `lab:profile --dry-run` MUST list the files and their declared tables and execute no
  SQL. `lab:profile --query=N` MUST run exactly one file.
- **FR-004**: A full run MUST produce three outputs: a table in the terminal (English — operator
  output), the complete results as JSON in `source_snapshots.profiling_results`, and
  `docs/reports/p1-profiling.md` **generated from that JSON**.
- **FR-005**: `source_snapshots.profiling_results` MUST be the authoritative record. The report MUST
  be regenerable from it alone, MUST be byte-identical when regenerated without a new run, and MUST
  contain nothing hand-maintained.
- **FR-006**: Each run MUST create a **new** `source_snapshots` row, so re-running produces a
  comparison rather than an overwrite. Every row MUST carry `snapshot_taken_at`, `loaded_at`, the
  MySQL version, the source database size, and `source_row_counts` for the eleven copyable tables.
- **FR-007**: The report and every console screen MUST display `snapshot_taken_at` beside the
  numbers. Nothing anywhere may gate, warn, or block on the snapshot's age.
- **FR-008**: No row read from a `profile_tables` table may be stored anywhere in the Lab — only
  counts and aggregates derived from it.

### The mirror schema

- **FR-009**: The Lab MUST gain **fifteen** tables, owned by Laravel migrations: `source_snapshots`,
  `source_categories`, `source_courses`, `source_chapters`, `source_lectures`, `source_quizzes`,
  `source_sections`, `source_questions`, `source_question_options`, `source_media`, `source_results`,
  `source_item_stats`, `source_option_stats`, `import_runs`, `import_errors`. There is **no**
  `source_answers`: individual answer events are aggregated, never mirrored (ADR-022).
- **FR-010**: Every mirror table MUST carry `source_system`, `source_id`, `source_created_at`,
  `source_updated_at`, `source_deleted_at`, `imported_at`, `import_run_id`, and `payload_hash`, with
  a UNIQUE constraint on (`source_system`, `source_id`) as the upsert target. `payload_hash` is
  populated on **every** table by the one rule in FR-018 — behavioural rows included, because the
  hash is what lets a re-run report zero updates cheaply.
- **FR-011**: **No mirror table may carry a `user_id` column**, nor any column capable of holding a
  name, email, phone, or national identifier.
- **FR-012**: Only the permitted columns may be copied. From `courses`: name, slug, category, status,
  start/exam dates and the three Telegram fields — **not** price, discount, conditions, meta fields,
  or images. From `lectures`: topic, order, chapter and timestamps — **not** `zoom_start_url`,
  `zoom_join_url`, `meeting_id`, `passcode`, `vimeo_id`, `bunny_id`, `youtube_id` or `upload_*`, some
  of which are credentials. From `quizzes`: **not** `user_id`.
- **FR-013**: `source_sections` MUST carry `stimulus_raw` (a faithful copy of `sections.description`),
  `stimulus_length`, `has_stimulus`, `is_long_stimulus` (> 200 characters) and `questions_count`.
  `sections.description` MUST be read and never assumed empty because it is absent from `$fillable`.
- **FR-014**: Indexes MUST be limited to those a downstream project will actually use:
  `source_questions(section_source_id)`, `source_question_options(question_source_id)`,
  `source_option_stats(question_source_id)`, `source_results(quiz_source_id)`,
  `source_results(student_ref)`. **No vector index and no trigram index** — there is nothing to index
  yet.
- **FR-015**: Each migration MUST document, in a comment, what was deliberately not copied and why —
  price, Zoom data, `user_id` — and MUST record the source's `sorte_order` typo where it is copied as
  `sort_order`.

### The derivation core

- **FR-016**: Correct-answer derivation MUST be
  `correct_option_ids = [o.id for o in options if o.deleted_at IS NULL and o.points > 0]`, yielding
  `single_correct` at 1, `broken_no_key` at 0, and `multi_key` above 1. `broken_no_key` questions are
  copied, flagged, escalated, and **never used** as answerable items. `correct_option_count` is
  mechanical and MUST be derived during the copy pass; `answer_key_state` is an interpretation and
  MUST stay **pending** until FR-061's decision is recorded.
- **FR-017**: Option index MUST be derived by `` ORDER BY `order` ASC, id ASC ``, identically
  everywhere, never abbreviated. `is_correct_derived` MUST be `points > 0`. A/B/C/D letters MUST NOT
  be stored — they may be synthesized from `option_index` at render time only.
- **FR-018**: `payload_hash` MUST be SHA256 over a key-sorted JSON serialization, computed by one
  mechanism for every mirror table: **each hashes its own copied source columns**, so a
  changed column is the only thing that makes a re-import write. `source_questions` is the **single
  exception** and MUST use §16's definition verbatim — `name`, `description`, `hint`, and the options
  ordered by `option_index` with `name` and `points` — so an edit to an option changes the question's
  hash. The same input MUST give the same hash; re-ordering the input options MUST NOT change it;
  changing an option's text MUST change it.
- **FR-019**: `student_ref` MUST be `HMAC-SHA256(pepper, user_id)`. The derivation MUST **throw** on
  an empty or missing pepper. The pepper MUST live only in `.env` and MUST never be stored in the Lab
  database.
- **FR-020**: `user_id` MUST be read, hashed, and discarded in the same statement. No variable, log
  line, error message, or `import_errors.context` payload may hold it.
- **FR-021**: Each of the four derivations MUST be unit-tested with no database, including the
  `order`-tie case and the empty-pepper case.

### The ETL structure

- **FR-022**: The system MUST provide `php artisan lab:import` with `--kind=bank|behaviour|all`,
  `--resume`, `--chunk=` and `--queue`, creating one `import_runs` row per run and linking every
  written row to it via `import_run_id`.
- **FR-023**: Writes MUST be upserts on (`source_system`, `source_id`). A matching `payload_hash`
  MUST increment `rows_unchanged` and write nothing; a differing hash MUST update and increment
  `rows_updated`; an unseen key MUST insert.
- **FR-024**: The import MUST be **idempotent**: a second run against the same snapshot MUST produce
  `rows_inserted = 0`, `rows_updated = 0`, `error_count = 0`.
- **FR-025**: The import MUST be **resumable**: `resume_cursor` records the table and the last
  confirmed `source_id`, updated after each confirmed batch, and `--resume` continues from there
  without duplicating a row or dropping one.
- **FR-026**: **Every write site MUST call `assertCopyable()`** on the source table before writing.
  There is no exception and no shortcut. A test MUST fail if the call is removed from any one site.
- **FR-027**: Every anomaly MUST be written to `import_errors` with its code, severity, source table,
  source id, message and context, and the batch MUST continue. A silent `try/catch` is a defect.
  `import_errors` is an **append-only per-run log**: every row is scoped by `import_run_id`, and no
  row is ever deleted or rewritten by a later run. A run that writes nothing therefore logs nothing,
  which is why FR-049's quality cards MUST NOT be built on this table.
- **FR-028**: `import_runs` MUST record `kind`, `started_at`, `finished_at`, `status`, `rows_read`,
  `rows_inserted`, `rows_updated`, `rows_unchanged`, `error_count`, and `resume_cursor`, and MUST
  link to the snapshot the run was made against.
- **FR-029**: `lab:import` MUST run **synchronously by default**, reporting progress and exiting
  non-zero on failure, so a run has a definite completion signal. `--queue` MUST dispatch the same
  work to the `database` queue instead, for the behavioural run that is meant to proceed unattended.
  **Both paths MUST share the same job classes, resume cursor and upsert** — a second implementation
  is a defect, because only one of the two would then be the tested one.
- **FR-030**: `lab:import --help` MUST carry the operating instructions — what each flag does, what
  `--resume` picks up, and what each error code means. **No runbook is written**; the help output is
  the import's documentation.

### The bank import

- **FR-031**: Tables MUST be imported in dependency order: categories → courses → chapters →
  lectures → quizzes → sections → questions → options → quiz_files.
- **FR-032**: **Soft-deleted rows MUST be copied**, with `source_deleted_at` preserved. They are
  excluded at analysis time, not at copy time.
- **FR-033**: Production identifiers MUST be preserved exactly and never regenerated;
  `categories.parent_id` MUST be copied as-is into `parent_source_id` and never repaired.
- **FR-034**: `source_questions` MUST carry `raw_text` (from `questions.name`, unmodified),
  `explanation_raw` (from `description`), `hint_raw`, `correct_option_count`, `answer_key_state`,
  `options_count`, `stem_char_length`, `has_html`, `has_img`, `is_stem_image_only`,
  `requires_media_review`, `source_origin` and `payload_hash`. `source_origin` MUST default to
  `unknown`, and nothing else may be claimed without evidence. `answer_key_state` MUST default to
  **pending** and MUST NOT be guessed at copy time.
- **FR-035**: `source_media` MUST copy both attachment levels (section and question) and MUST set
  `path_unverified = true` on every row — Production storage is not reachable locally, and what
  cannot be verified is not presented as verified. Images inside `questions.name` are a second,
  independent media path, surfaced alongside it.
- **FR-036**: The copied row count of each table MUST equal the source count recorded by the
  profiling run, soft-deleted rows included.

### The behavioural import

- **FR-037**: `source_results` MUST be imported first, carrying `quiz_source_id`, `total_points`,
  `student_ref`, `attempt_index`, and `duration_estimate_seconds`.
- **FR-038**: `attempt_index` MUST be derived by creation-time ordering within
  (`student_ref`, `quiz_source_id`) — the source carries no attempt number.
- **FR-039**: `duration_estimate_seconds` MUST be derived as `updated_at − created_at` and MUST be
  labelled an approximation **in the column name itself**, so it is never later read as a real test
  duration.
- **FR-040**: Individual answer events MUST NOT be mirrored. `question_result` MUST be read as
  aggregates pushed down into the source and stored as `source_item_stats` (`n`, `n_correct`,
  `p_value`, and the corrected-total components `m1_corrected`, `m0_corrected`, `sd_corrected`) and
  `source_option_stats` (`chosen_n`, `chosen_share`, `is_key`), each at both the `active` and `all`
  scope. `question_result` MUST be on `profile_tables`, so `assertCopyable()` refuses it by name.
  Every ratio and mean MUST cast to DOUBLE **before** aggregating — MySQL quantizes `AVG()` over an
  integer expression and decimal division to 4 decimal places. `source_option_stats` MUST include
  never-chosen options with `chosen_n = 0`, and `source_item_stats` MUST include questions with no
  answer data at `n = 0` (ADR-022).
- **FR-041**: The elapsed time of the behavioural run MUST be recorded in `import_runs`. It is a
  number P3 needs to size its own batches — **not a gate**. Mirror writes MUST go through one batched
  upsert that preserves idempotency, the inserted/updated/unchanged counters, `payload_hash`
  semantics and resume.

### The validation checks

- **FR-042**: Thirteen checks MUST run during import, each writing its own code to `import_errors`:
  `MISSING_OPTIONS`, `EMPTY_STEM`, `ZERO_CORRECT`, `MULTI_CORRECT`, `DUPLICATE_OPTION_TEXT`,
  `OPTION_ORDER_TIE`, `BROKEN_HTML`, `STEM_IMAGE_ONLY`, `ORPHAN_SECTION`, `ORPHAN_QUIZ`,
  `CATEGORY_ORPHAN_PARENT`, `STIMULUS_NO_QUESTIONS`, `QUESTION_NO_SECTION`.
- **FR-043**: `ZERO_CORRECT` MUST carry `severity = error` — it affects a student now. `BROKEN_HTML`
  MUST NOT stop the batch: it is isolated, logged, and the run continues.
- **FR-044**: The codes MUST live in **one** enumeration with a human-readable description per case,
  read by both the console and `lab:import --help`, so a code has one meaning.
- **FR-045**: The `ZERO_CORRECT` rate among active questions MUST equal query 3's result. A
  discrepancy MUST be treated as a defect in the profiling run or in the mirror, and resolved before
  acceptance.
- **FR-046**: No check may repair, normalize, or drop a row. Anomalies are recorded beside faithful
  copies.

### The inventory console

- **FR-047**: The inventory console MUST be added to the **existing** Filament panel, which becomes
  Arabic with correct RTL globally. Reviewer-facing text is Arabic; **technical identifiers stay
  English**, and so does operator-facing output — P0's health screen keeps its English check names
  and values unchanged inside the RTL shell, and `lab:health` on the CLI is untouched. No second
  panel is created.
- **FR-048**: Every screen MUST carry a fixed header showing `snapshot_taken_at`, the row count, and
  the date of the last import run. **Amended 2026-08-27 (operator decision)**: the header is
  Dashboard-only, not every screen. The original render hook duplicated it on every Resource page
  (above the breadcrumbs) and, on the Dashboard itself, a *second* time there too — Filament
  auto-renders every discovered widget, `SnapshotHeader` included, as the page's own content grid,
  so the explicit render-hook mount was always a redundant second copy on the Dashboard as well. One
  instance remains: the Dashboard's own widget-grid render. Resource pages no longer show
  `snapshot_taken_at` at all — SC-015's "no screen shows a number without `snapshot_taken_at` beside
  it" is knowingly no longer met outside the Dashboard; the operator traded that guarantee for not
  having the header duplicated across the console.
- **FR-049**: The console MUST present, sourced from the **mirror's own columns** — never from the
  source, and never from `import_errors`, so that a no-op re-import cannot make a problem appear to
  have been fixed: total
  questions (active / soft-deleted), questions by category, by course, by quiz, the option-count
  distribution, answer-key integrity (single / none / multi), questions with no explanation,
  questions containing HTML, questions containing images, sections carrying shared text, questions
  needing media review, and import errors by code.
- **FR-050**: **Every number MUST be clickable** through to the filtered list and onward to the
  individual question with its ordered options and its derived correct answer.
- **FR-051**: An import-errors screen MUST be filterable by code, by severity, and by source table.
  It MUST **name the import run it is displaying** and default to the latest run that actually wrote
  rows, with earlier runs still reachable — the log is history, and history is not overwritten.
- **FR-052**: The suppression rule MUST be applied wherever a group count is displayed: `n < 10`
  publishes nothing, `n < 30` publishes partially, `n ≥ 30` publishes fully.
- **FR-053**: The console MUST state the two structural limits it cannot resolve: media paths are
  unverified, and a question with no answer row cannot be distinguished between "not answered" and
  "not shown".

### Guarantees and wrap-up

- **FR-054**: `NoPiiInLabSchemaTest` MUST be extended over all fifteen tables and MUST fail if a
  column appears carrying `user_id`, email, phone, name, or national identifier.
- **FR-055**: A copy-guard test MUST prove that every ETL write site passes through
  `assertCopyable()`, and that attempting to copy a `profile_tables` table throws by name while
  reading a count from it still succeeds.
- **FR-056**: Idempotency, resume, the four derivations, and the thirteen validators MUST each be
  covered by tests. `ReadOnlyGuardTest` MUST stay green unmodified.
- **FR-057**: At least one statistic displayed on the console MUST be reproduced from the raw mirror
  rows in a test.
- **FR-058**: `lab:health` MUST report 10/10 with exit 0 after every phase, not only at the end.
- **FR-059**: `README.md` MUST gain a P1 section covering the two commands and the one screen, and
  `apps/lab/.env.example` MUST list any new key with no value.
- **FR-060**: §13 of the program plan MUST carry an `**Updated**` note pointing at the generated
  report — the estimate stays visible beside the measurement, so "by how much were we wrong?" stays
  answerable. `docs/runbooks/snapshot.md` MUST record the fixed-copy decision once. `CLAUDE.md` and
  `AGENTS.md` MUST carry P1's measured facts, byte-identical in both. **No new runbook, ADR,
  acceptance record, or handover document is produced.**

### The three findings that gate downstream work

- **FR-061**: The **meaning of multi-key** MUST be settled from the results of queries 3 and 4 — is
  more than one correct answer supported, or are these data-entry errors? This is a domain decision,
  not a developer's. It blocks **`answer_key_state`, not the copy**: the bank import runs to
  completion with `answer_key_state` pending, and a separate **idempotent backfill pass** sets it
  once the decision is recorded, re-runnable without touching any other column. No question may leave
  the pending state on a guess, and acceptance is not reached while any active question is still
  pending.
- **FR-062**: The **enrolment question** — `course_user` versus `course_order` — MUST be answered
  from queries 15 and 16 and pinned in the documentation, because P5 and P6 build on the answer.
- **FR-063**: If the `correct_count = 0` rate exceeds **2%**, the broken-question list becomes the
  first deliverable handed to the team, the dedup track in P2 stops, and the remaining scope of this
  feature is reconsidered **before** it is built. Everything else in the pack is recorded and read,
  and blocks nothing.

---

## Key Entities

- **Snapshot record** — one row per profiling run against the fixed 2026-08-07 copy, holding the
  authoritative measurement as JSON, the per-table row counts, and the path of the report generated
  from it. The frame every number in the program is read in.
- **Mirror table** — a faithful local copy of one source table, keyed by (`source_system`,
  `source_id`), carrying the source's own timestamps including `deleted_at`, the run that last wrote
  it, and a payload hash. Never cleaned, never normalized, never judged.
- **Question** — the central mirror entity: the original text unmodified, its explanation and hint as
  they exist, its derived answer-key state, its structural flags, and its payload hash. It belongs to
  exactly one section, which belongs to exactly one quiz.
- **Option** — a candidate answer with its points, its source order as recorded, and its derived
  stable index. Correctness is derived from points, because the source has no correctness column.
- **Stimulus** — the shared text on a section, with its length and its "long" flag. Whether
  passage-based questions are an add-on or a core requirement is decided by these numbers.
- **Attempt** — one quiz attempt, pseudonymized, mirrored row-for-row in `source_results`. Bounded
  by attempts and genuinely row-level: cohorts and corrected totals need it.
- **Answer statistics** — what the (attempt × question) grain is reduced to. The events themselves
  are unbounded and never annotated individually, so they are stored as per-question and per-option
  aggregates. The discrimination index is computed by P3 from the stored components (ADR-022).
- **Import run** — the record of one import: what it read, what it wrote, what it left alone, what
  went wrong, and where to resume from.
- **Import error** — one recorded anomaly: code, severity, where it was found, and enough context to
  act on. The visible alternative to a silent `try/catch`.
- **Derivation core** — the four deterministic rules everything downstream depends on: answer-key
  state, option index, payload hash, student ref. Tested before they touch a row.

---

## Success Criteria

- **SC-001**: All eighteen §6 queries execute through the guarded connection and their results are
  persisted as JSON; the report regenerates from that JSON identically, with no hand-written prose.
- **SC-002**: A query file declaring a forbidden table makes the command fail, naming the table,
  before any SQL executes.
- **SC-003**: The re-measured counts match P0's baseline against the same snapshot — 29,142
  questions, 124,549 options, 3,362 quizzes, 231 courses.
- **SC-004**: The multi-key meaning, the enrolment table, and the broken-question rate all have
  answers before `answer_key_state` is derived for the mirror.
- **SC-005**: Every question in the source exists in the mirror with its Production identifier
  unchanged, soft-deleted rows included with `source_deleted_at`.
- **SC-006**: The derived statistics reconcile exactly to the source: `SUM(source_item_stats.n)`
  and `SUM(source_option_stats.chosen_n)` at the `all` scope each equal `COUNT(question_result)`, and
  a sample of stored statistics recomputes from the raw source rows.
- **SC-007**: Two consecutive imports produce 0 inserts, 0 updates and 0 errors on the second.
- **SC-008**: An import interrupted mid-batch and resumed loses no row and duplicates none.
- **SC-009**: No column in any of the fifteen tables can hold personal data, proven against the
  schema; `user_id` appears in no column, no log, and no error context.
- **SC-010**: Removing `assertCopyable()` from any single ETL write site fails a test.
- **SC-011**: No row from any of the six profile-only tables is stored in the Lab, and each is
  refused by name when copying is attempted.
- **SC-012**: Zero rows are written by the Lab to `injazedu.co` or to the local snapshot.
- **SC-013**: All thirteen validation codes are produced by their conditions, filterable by code,
  severity and table, with the batch completing in every case.
- **SC-014**: The broken-question rate in the mirror equals the rate in the profiling run.
- **SC-015**: Every number on the console reaches the rows it was built from in one click, and no
  screen shows a number without `snapshot_taken_at` beside it. **Amended 2026-08-27**: the second
  half no longer holds outside the Dashboard — see FR-048's amendment.
- **SC-016**: At least one console statistic is reproduced from raw rows in a test.
- **SC-017**: `lab:health` passes 10/10 with exit 0 at the end of every phase.
- **SC-018**: Following the README from a clean Lab database reaches a populated inventory console.
- **SC-019**: Every import run records its read/insert/update/unchanged counts, its error count, its
  elapsed time and its resume cursor, whether it ran inline or through `--queue`, and the two paths
  produce the same mirror from the same input.
- **SC-020**: No active question is left with `answer_key_state` pending at acceptance, and re-running
  the backfill pass changes nothing.
- **SC-021**: §13 of the program plan points at the generated report with the estimate still visible
  beside the measurement; the environment template lists every new key with no values; and no new
  runbook, ADR, acceptance record, or handover document was produced.

---

## Assumptions

Recorded so they are visible rather than implied. Measurements are from 2026-08-21/22/23 unless
noted.

- **The snapshot is fixed at 2026-08-07** and is never refreshed. Nothing here blocks on its age; the
  date travels with every number as context.
- **P0's counts are a baseline to confirm, not to replace.** A mismatch against the same snapshot is
  a bug in `lab:profile`.
- The bank is ~29,142 questions and ~124,549 options; the behavioural side is 1,136,204 results and
  13,776,378 answer events; the source database is ~2,189 MB.
- **Attempts are mirrored; answer events are aggregated** (ADR-022, superseding this spec's original
  assumption that both were copied in full). The original rationale was that P3's discrimination
  index needs the (attempt × question) grain and that pre-aggregation would either kill P3 or force a
  second full pass. Building it settled both halves: the raw mirror cost 13.8M rows and 3.8 GB, and
  every consumer named in the program reads a `GROUP BY` over it — including the point-biserial,
  whose corrected-total components come out of the same aggregate. So the grain is preserved in the
  statistics, not in the rows.
- The deciding argument is not storage but **boundedness**: answer events grow with students × time
  without limit, while the aggregate's size is fixed by the question count. The raw mirror is the one
  component that would not survive pointing the Lab at a much larger platform.
- **Re-slicing is not foreclosed.** The raw rows remain in the frozen 2026-08-07 snapshot on this
  machine, and recomputing the aggregate takes ~5 s, so a different window or grain costs one query.
  This is what keeps constitution §V's "reproducible from raw rows" satisfied — against the snapshot,
  which §III already defines as the reproducibility base.
- **Fifteen tables, not the twelve §16 lists.** `source_chapters` and `source_lectures` are added:
  §14.2 permits them (title and order only), `quizzes.lecture_id` is otherwise an uninterpretable
  number, and `chapters` is the only parent of `lectures`.
- **No ADR is written in this feature.** None of its decisions is architectural *and* durable *and*
  expensive to reverse — a command is a command, the full mirror is a direct application of §16, and
  the §13 note is a document edit.
- The three Telegram fields ride on `source_courses` rather than a separate channels table; a
  channels table with an `is_public` flag is P6's concern, and P1 only needs the coverage number
  query 17 produces.
- `import_runs` links to the most recent `source_snapshots` row for the fixed snapshot, so a run's
  numbers can always be read beside the measurement they were checked against.
- `requires_media_review` and `questions_count` are set in a second pass, after the tables they
  depend on exist — the mandatory import order makes a single-pass derivation impossible. The
  `answer_key_state` backfill (FR-061) uses the same mechanism, so there is one idempotent
  second-pass pattern rather than two.
- Chunk size starts at 10,000 rows and is tuned by measurement, not by a gate.
- `path_unverified` is `true` on every media row in this project; verification needs Production
  storage, which is not reachable locally.
- The Lab application never writes to MySQL, and the operator's own freedom to inspect or modify the
  local copy is unaffected — read-only is a property of the **application**, enforced in three layers.
- Ollama, embeddings, and pgvector are untouched by this feature; they exist and stay idle.

---

## Dependencies

- `004-handover-and-p1-readiness` accepted: two allowlists, eighteen unexecuted query files, the
  one-command stack, `lab:health` 10/10.
- The contract in `specs/004-handover-and-p1-readiness/contracts/source-access-and-stack.md` — this
  feature's ETL is its second party.
- MySQL 9.1 reachable on 127.0.0.1:3306 with the 2026-08-07 snapshot; Lab Postgres 17 on 5433.
- `STUDENT_REF_PEPPER` set, stored outside Git **and off this machine**. This is blocking before the
  behavioural import: once ~1.1 million `student_ref` values are written, changing the pepper orphans
  every behavioural row with no way to re-link, and there is no backup by program decision.
- Two decisions that are domain expertise rather than development work: the meaning of multi-key, and
  the enrolment table. Both are answered from the profiling output; the first blocks the bank import.
- No connection to `injazedu.co`, in this or any other feature.

---

## Handoff to P2 and P3

```text
P2 receives: source_questions.raw_text (unmodified) · source_question_options ordered by a stable
             option_index with is_correct_derived · source_sections.stimulus_raw + is_long_stimulus
             · answer_key_state · payload_hash · the true active question count
             — and no clean_text, no search_text, no similarity hash, not one vector.

P3 receives: source_results (student_ref · quiz · total_points · attempt_index), plus
             source_item_stats (n · n_correct · p_value · m1_corrected · m0_corrected ·
             sd_corrected) and source_option_stats (chosen_n · chosen_share · is_key), each at the
             active and all scope. r_pbis is P3's to compute from those components — no raw answer
             rows, and none needed. No AI, no embeddings, no taxonomy.

Both read their numbers from source_snapshots.profiling_results rather than re-querying the source,
so the program keeps one version of the truth.
```
