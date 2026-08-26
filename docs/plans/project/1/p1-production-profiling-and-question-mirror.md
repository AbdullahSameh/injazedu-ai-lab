# P1 — Production Profiling & Question Mirror
## Implementation Plan — Second Project

**Project:** P1 — Production Profiling & Question Mirror
**Name in v1.0 (§11):** *Question Data Mirror & Inventory* — kept for reference only; **v2.0 governs**
**Order:** Second in the program — depends on P0, and is depended on by P2, P3, P4, P5
**Version:** 2.0 — leaned 2026-08-25 (v1.0 dated 2026-08-23)
**Governing reference:** §16 of `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0)
**Delivered as:** one Spec Kit feature — see §7.1
**Status:** Ready for implementation
**Effort estimate:** ~9.5 focused working days for a single developer

> **What changed in v2.0.** Three operator decisions were applied. (1) The local Production copy is
> **fixed at 2026-08-07** for the whole program — the snapshot-refresh phase and its blocking gate
> are gone. (2) Documentation is produced only where it has continuing practical value — the
> handover phase is gone, the profiling report is now **generated** from stored JSON rather than
> hand-written, and the "written and reviewed before any ETL" gate is replaced by the three findings
> that genuinely change code. (3) P1 is **one spec**, not four increments.
> No engineering requirement was dropped: the fourteen tables, the derivation core, idempotency,
> resume, the copy guard, the PII schema test and the clickable console are all intact.

---

# 1. Context and Goal

## 1.1 Why Measurement Comes Before Copying

v1.0 opened this project with a single sentence: "import 25 thousand questions." v2.0 reversed the
order, and the reason deserves to be stated plainly before a single line of code:

```text
We do not build on an unmeasured number.
```

Every estimate in §13 of the governing plan — the number of embedding calls, the number of candidate
pairs, LLM hours, human review hours — rests on an **assumed** number. And P0 proved the assumption
wrong by 16.6% on the very first number that was actually measured (29,142 questions, not ~25,000).
The other numbers have never been measured at all: how many questions have no correct answer? How
many have two? What percentage contains HTML? How many sections carry shared stimulus text? Which
table actually records enrollment?

This is not statistical curiosity. Several of them **change a downstream project** — and §6.3 of the
governing plan names the change explicitly. Three of them change **this** project's code, which is
why profiling runs first (§7 Phase 2).

## 1.2 The Governing Principle

```text
Measure first. Store the measurement. Then copy.
And the copy is faithful: no cleaning, no normalization, no deletion, no judgment.
```

P1 is a **mirror**, not a processor. Every meaningful transformation (Arabic normalization,
similarity, classification) belongs to P2 and beyond. P1 does exactly three things: it **measures**,
it **copies faithfully**, and it **makes what it copied visible**.

## 1.3 Definition of Done

> One command (`lab:profile`) measures the bank and persists the measurement; one command
> (`lab:import`) builds the complete mirror idempotently and resumably; and an Arabic inventory
> console makes **every number on it clickable** all the way down to the questions themselves — with
> not a single PII column in the Lab database, proven by a test.

## 1.4 What This Project Unblocks

| Next project | What it needs from P1 |
|--------------|------------------------|
| P2 — Duplicate Intelligence | `source_questions.raw_text` + options ordered by `option_index` + stimulus fields on `source_sections` (§8) + `payload_hash` |
| P3 — Item Statistics | `source_results` with `student_ref`, plus `source_item_stats` and `source_option_stats` — **and that is all it needs**; no AI, no embeddings |
| P4 — Quality Audit | The question inventory + validation flags (`answer_key_state`, `has_html`, `has_img`) |
| P5 — Taxonomy & Coverage | Question distribution across `categories` and `courses` — the basis of the coverage map |
| P9 — Copilot | The fill rate of `description`/`hint` (settles whether any explanation exists to build on at all) |

---

# 2. What P1 Inherits from P0

## 2.1 Ready, and Not to Be Rebuilt

```text
injazedu connection with three read-only layers        (ADR-021, tested)
Two allowlists in apps/lab/config/lab.php:
  source_tables  (11)  <- what may be copied  -> assertCopyable()
  profile_tables (6)   <- counts only         -> assertReadable()
App\Support\SourceReader                               (the only approved read path)
PostgreSQL 17 + pgvector + pg_trgm on 5433             (ready for P1 and P2 tables)
Laravel 13 + Filament 5 on PHP 8.4                     (owns all migrations — ADR-013)
database queue                                         (ADR-011 — ready for ETL batches)
scripts/lab-stack.sh up | down | status
php artisan lab:health                                 (10/10, exit 0, 7.058 s cold)
sql/profiling/ — 18 queries written and not yet run
STUDENT_REF_PEPPER · SNAPSHOT_TAKEN_AT · EMBEDDING_CONFIG_VERSION in .env
```

`lab:health` with ten green checks is **the instrument this project is measured by**: every phase
leaves it green, or it stops.

## 2.2 The Snapshot Is Fixed, and P0's Numbers Are the Baseline

The local Production copy is dated **2026-08-07** and is the source for the entire local program
(operator decision 2026-08-25; core plan §14.1, constitution III). It is **not refreshed** before
P1, P2, or any later project, and **nothing in this plan blocks on its age**. `snapshot_taken_at`
travels with every number as context.

That makes P0's measurements a **baseline to confirm**, not prior numbers to replace. Phase 2
re-measures them against the same snapshot; a mismatch means a bug in `lab:profile`, not bank drift:

| Item | P0 measurement (2026-08-07 snapshot) | Status in P1 |
|------|--------------------------------------|--------------|
| `questions` | 29,142 | Re-measured by query 1 — must match |
| `options` | 124,549 | Re-measured — must match |
| `quizzes` | 3,362 | Re-measured — must match |
| `results` | 1,136,204 | Re-measured — sizes Phase 7 |
| `question_result` | 13,776,378 | Re-measured — sizes Phase 7 |
| `courses` | 231 | Re-measured — must match |
| Database size | 2,189 MB | Re-measured |
| `snapshot_taken_at` | 2026-08-07 | **Unchanged** — fixed for the program |

---

# 3. Deviations and Approved Decisions

§16 was written at program level. These operational decisions are settled and are not reopened
during implementation.

## 3.1 The Three Decisions

### Decision 1 — `php artisan lab:profile`, Not a Direct `mysql` Client

`sql/profiling/README.md` originally documented execution like this:

```sh
mysql -h 127.0.0.1 -u root injazedu < sql/profiling/01-bank-size.sql
```

That **bypasses all three layers**: a direct root connection, no listener, no allowlist. The layers
exist to prevent mistakes, and the first real piece of work in the program must not begin by
stepping outside them.

**Decision:** A Laravel command runs each of the eighteen files **over the `injazedu` connection**:

```text
Layer 1  <- applies automatically (no write target on the connection)
Layer 2  <- applies automatically (the listener throws on any non-read statement)
Layer 3  <- applies by declaration: each file carries a declared table list, passed
            through assertReadable() before execution. That list already exists in
            the table in sql/profiling/README.md
```

Layer 3 does not apply automatically to raw SQL (because `SourceReader::table()` returns a builder
for one table, not a SQL executor). Enforcing it by declaration means a new file that reads `users`
**will not run** unless its author lies in the declaration — and that is intent, not accident, which
is precisely the boundary ADR-021 admits it does not cross.

**Additional output:** a recorded run instead of manually copied results — one row in
`source_snapshots` carrying the full JSON, plus a markdown summary generated from it. Re-running
produces a comparison, not new prose.

### Decision 2 — A Complete, Faithful Behavioural Mirror, Chunked and Resumable
### — REVISED 2026-08-26 (ADR-022): attempts mirrored, answer events aggregated

§16 lists `source_results` and `source_answers` among P1's tables without settling their size.
(The latter was dropped on 2026-08-26 — see the revision note below.)
Measurement settles it: **~1.1 million + ~13.8 million rows**.

**Decision:** They are copied in full. `user_id` becomes
`student_ref = HMAC-SHA256(pepper, user_id)` at the very moment it is read.

**Why (as originally written):** P3 computes the **discrimination index (point-biserial)**, the
correlation between a question's score and the test score for each attempt. That requires raw rows at
the (attempt × question) grain. Any pre-aggregation in P1 either kills P3 or forces a second full ETL
pass over 13.8 million rows. And storage is not a constraint: ~1–2 GB against 149 GB free.

**What building it showed (2026-08-26, ADR-022):** the premise was wrong on both halves. The
point-biserial's corrected-total components (`M₁`, `M₀`, `SD`) come out of the *same* `GROUP BY` as
the p-value, so the grain is preserved in the statistics rather than in the rows — P3 is not killed.
And the "second full pass" is a 5-second query against a snapshot that never changes. `source_results`
(1.1M) is still mirrored in full; `question_result` (13.8M) is not. The deciding argument turned out
not to be storage but **boundedness**: answer events grow with students × time without limit, while
the aggregate is bounded by the question count.

**Effect:** Phase 7 is a standalone phase with its own time budget and an explicit resume-cursor
design.

### Decision 3 — The Measurement Is Data First, a Document Second

§16 makes "§13 updated with real numbers" one of P1's deliverables. §13 lives inside the governing
plan v2.0.

**Decision:** the authoritative record of the measurement is
`source_snapshots.profiling_results` (JSONB) — queryable, comparable across runs, and readable by
downstream code. `docs/reports/p1-profiling.md` is **generated from that JSON** by `lab:profile`;
it is regenerable and never hand-maintained. §13 of the governing plan gets an
`**Updated 2026-XX-XX**` note pointing at it — the pattern already in use there (§12.3, §14.2 and
§14.6 all carry notes of that shape).

**Why:** keeping the old estimate visible next to the measurement is what made P0's revisions
reviewable. Replacing the number in place erases the question "by how much were we wrong?" — and
downstream projects need that answer. Generating the prose rather than writing it means the two can
never drift.

## 3.2 Two Smaller Decisions — Declared, Not Implicit

**(a) Fourteen tables, not twelve.** §16 lists twelve tables and omits `source_chapters` and
`source_lectures`. Decision: both are added.

- §14.2 explicitly permits them (**title and order only**).
- `quizzes.lecture_id` exists in the schema and means "post-lecture quiz" (§5) — without a lectures
  table it is a bare number that can be neither displayed nor interpreted.
- `chapters` is the only parent of `lectures` in the schema, so the second cannot be taken without
  the first.

From `lectures`, only `topic`, `sorte_order`, `chapter_id` and the timestamps are copied. **No**
`zoom_start_url`, no `meeting_id`, no `passcode` — these have nothing to do with questions, and some
of them are credentials.

**(b) No ADR in P1.** The constitution: an ADR requires a decision that is architectural **and**
durable **and** expensive to reverse. None of the decisions above qualifies: `lab:profile` is an
ordinary command, the full mirror is a direct application of what §16 says, and the §13 note is a
document edit. They are recorded here and in the spec, and no ADR files are created.

---

# 4. Scope

## 4.1 In Scope

```text
php artisan lab:profile           run the §6 suite over the guarded connection
source_snapshots.profiling_results   the persisted measurement (JSONB)
docs/reports/p1-profiling.md      generated from that JSON + the §13 note
14 mirror tables in PostgreSQL    (migrations in Laravel — ADR-013)
Deterministic derivation core     §5.1 · §5.2 · payload_hash · student_ref
php artisan lab:import            idempotent, chunked, resumable ETL
13 validation checks              all write to import_errors and swallow nothing
Filament inventory console in Arabic    every number clickable
Tests: the core · the guards · idempotency · resume · reproducing a statistic from raw rows
README.md P1 section + any new .env.example key
```

## 4.2 Out of Scope — Explicitly

```text
Refreshing the snapshot                              -> cancelled; fixed at 2026-08-07
Arabic normalization · clean_text · search_text      -> P2
Any similarity hash (text, or text+options)          -> P2
Any embedding · any neighbour search · pgvector      -> P2
Any duplicate detection, clustering, or judgment     -> P2
Any item statistics (p-value, discrimination,
  distractors)                                       -> P3
Any classification, taxonomy, or coverage map        -> P5
Any LLM call whatsoever                              -> P2 and beyond
Any write to MySQL by the Lab application            -> forbidden program-wide
Any connection to injazedu.co                        -> forbidden program-wide (§3.1)
Any backup or restore                                -> cancelled program-wide (§14.6)
Any gate or criterion on a memory number             -> cancelled (constitution VII)
Any gate on the snapshot's age                       -> cancelled (constitution III)
A new runbook, acceptance record, or handover doc    -> cancelled (constitution, doc policy)
Correcting any data in the source                    -> the source is read, never corrected
```

**P0's rule carries over unchanged:** if you find yourself writing logic that compares two questions
to each other, you are in the wrong project.

**A note on correction:** if profiling reveals broken questions (no correct answer), P1's output is a
**list** handed to the team to fix in the Production admin console. The Lab does not correct, and
does not propose automatic corrections, in this project.

---

# 5. Architecture — Data Flow

Two paths leave the source, and one of them is **deliberately blocked**:

```text
   MySQL 9.1 · 127.0.0.1:3306 · database injazedu  (snapshot 2026-08-07; read, never modified)
   +------------------------------------------------------------------+
   |  50 tables                                                        |
   |  |-- 11 on source_tables     (read and copied)                    |
   |  |--  6 on profile_tables    (read as counts only)                |
   |  +-- 15 refused by name      (users among them)                   |
   +------------------------------------------------------------------+
                 |                                    |
   Layer 1: no write target on the connection      Layers 1 and 2, same
   Layer 2: listener throws on any non-read
                 |                                    |
                 v                                    v
      assertCopyable(table)                  assertReadable(table)
      Layer 3 — the copy list                Layer 3 — both lists
                 |                                    |
                 v                                    v
   +-----------------------------+      +-------------------------------+
   | ETL jobs  (queue: database) |      | lab:profile — 18 queries      |
   | chunked · resumable         |      | counts and aggregates only    |
   | user_id -> student_ref      |      +-------------------------------+
   +-----------------------------+                    |
                 |                                    v
                 v                    source_snapshots.profiling_results (JSONB)
   +-------------------------------------+   -> generates docs/reports/p1-profiling.md
   | PostgreSQL 17 · 5433 · injazedu_lab |            |
   | 14 mirror tables · no PII column    |            v
   | import_runs · import_errors         |     X no row from profile_tables
   +-------------------------------------+       is ever stored. Ever.
                 |
                 v
   The inventory console (Filament · Arabic · RTL) — every number clickable
```

**The security property is exactly that difference:** the left path ends in stored rows, the right
path ends in counts. The union of the two lists is **never used** as a copy check —
`assertCopyable()` accepts `source_tables` alone, and P0's `SourceTableAllowlistTest` pins that down.

**The backstop is the schema, not the query:** `NoPiiInLabSchemaTest` asserts that no column in the
Lab database is capable of holding personal data. That test does not care what some passing query
selected, which is why it stays valid however far the read list grows.

---

# 6. Mirror Table Schema

**Fifteen tables** (ADR-022: `source_answers` dropped, two stats tables added). Every column below is derived from `docs/schema/injazedu-db-schema.md` — not
from assumption. Where the plan and the schema disagree, **the schema governs** (constitution).

## 6.1 Columns Common to Every Mirror Table

```text
source_system      TEXT      constant: 'injazedu_production'
source_id          BIGINT    the Production identifier as-is — never regenerated
source_created_at  TIMESTAMP
source_updated_at  TIMESTAMP
source_deleted_at  TIMESTAMP NULL   <- soft-deleted rows are copied, not excluded
imported_at        TIMESTAMP
import_run_id      BIGINT    <- which run last wrote this row
payload_hash       CHAR(64)  <- SHA256, the basis of idempotency
```

The logical primary key in every mirror table is (`source_system`, `source_id`) with a UNIQUE
constraint, and the upsert targets it; the two derived stats tables use a natural key instead.
**There is no `user_id` column in any of the fifteen tables.**

## 6.2 The Tables

### `source_snapshots` — the register of the snapshot and its profiling runs

```text
id · snapshot_taken_at · loaded_at · mysql_version · source_database_size_mb
source_row_counts JSONB      <- the count of each of the 11 tables at profiling time
profiling_report_path TEXT   <- docs/reports/p1-profiling.md
profiling_results JSONB      <- the full lab:profile output — the authoritative record
notes TEXT
```

**Every report and every screen displays `snapshot_taken_at` from here** (§16 Risks,
constitution VI). One row per profiling run against the fixed 2026-08-07 snapshot, so re-running
`lab:profile` produces a comparison rather than an overwrite.

### `source_categories`

```text
source_id · name · slug · sort_order · parent_source_id · image
source_deleted_at · the common timestamps
```

**Two traps in the schema:** `parent_id` is typed `INT` while `id` is `BIGINT UNSIGNED`, and there is
**no FK** (§9 item 2). So expect orphans and cycles. The rule: `parent_id` is copied as-is into
`parent_source_id`, and every orphan is logged in `import_errors` under the code
`CATEGORY_ORPHAN_PARENT` — never repaired, never dropped. `sorte_order` (a typo in the source,
§9 item 11) is copied as `sort_order`, with the original documented in a migration comment.

### `source_courses` — **metadata only**

```text
source_id · name · slug · category_source_id · status · start_date · exam_date
telegram_channel · telegram_group · telegram_private     <- the basis of P6 (query 17)
source_deleted_at · the common timestamps
```

**Not copied:** `price`, `discount`, `course_conditions`, `meta_*`, images. §14.2 says
"courses (metadata only)", and price is not metadata any question analysis needs.

### `source_chapters` · `source_lectures` — title and order only (§3.2 a)

```text
source_chapters : source_id · title · sort_order · course_source_id · source_deleted_at
source_lectures : source_id · topic · sort_order · chapter_source_id · source_deleted_at
```

**Not copied from `lectures`:** `zoom_start_url`, `zoom_join_url`, `meeting_id`, `passcode`,
`vimeo_id`, `bunny_id`, `youtube_id`, `upload_*`. Some are credentials, and all are outside the scope
of questions.

### `source_quizzes`

```text
source_id · name · slug · description · status · sort_order · duration · hint
course_source_id   NULL => a general/open quiz   (query 7)
category_source_id · lecture_source_id · source_deleted_at
```

**`user_id` is not copied** (the quiz author) — it is an FK into `users`. Attribution at the quiz
level is lost, and that is acceptable: §5 already states there is no author at the question level.
`duration` covers the whole quiz; **there is no per-question timing** in the source (§5).

### `source_sections` — and this is where the stimulus lives (§8)

```text
source_id · quiz_source_id · name · order · source_deleted_at
stimulus_raw        TEXT   <- a faithful copy of sections.description
stimulus_length     INT    <- CHAR_LENGTH, computed at copy time
has_stimulus        BOOL   <- stimulus_raw is non-empty
is_long_stimulus    BOOL   <- > 200 characters (query 12's threshold)
questions_count     INT    <- derived, to expose "stimulus with no questions"
```

**Important:** `description` is **not** in `Section::$fillable` in the Production code (§9 item 7) —
but the column exists and may well be populated. **It is read, and never assumed empty.** This field
is the entire substance of §8 (STEP / IELTS / verbal aptitude), and the value of `is_long_stimulus`
from query 12 is what decides whether §8 is an "add-on" or a **core requirement** (§11).

### `source_questions` — the central table

```text
source_id · section_source_id · order · source_deleted_at
raw_text            TEXT    <- questions.name (LONGTEXT) as-is, never modified
explanation_raw     TEXT    <- questions.description   (there is no explanation column — §5)
hint_raw            TEXT    <- questions.hint
correct_option_count INT    <- derived (§5.1)
answer_key_state    ENUM    <- single_correct | broken_no_key | multi_key   (§5.1)
options_count       INT
stem_char_length    INT
has_html            BOOL    <- contains '<'
has_img             BOOL    <- contains '<img'
is_stem_image_only  BOOL    <- no text after stripping tags
requires_media_review BOOL  <- has a quiz_file of type audio/video  (§8 item 5)
source_origin       ENUM    <- authored | book_derived | unknown | suspected_official
                               defaults to unknown, and nothing else is claimed
                               without evidence  (§14.5)
payload_hash        CHAR(64)
```

`payload_hash` = SHA256 over a **key-sorted** JSON serialization of:
(`name`, `description`, `hint`, and the options ordered by `option_index` with `name` and `points`).
That is §16's definition verbatim, and it is what lets a re-import know what actually changed.

### `source_question_options`

```text
source_id · question_source_id · raw_text · points
source_order        INT   <- options.order as-is (may repeat within a question!)
option_index        INT   <- derived: ORDER BY `order` ASC, id ASC   (§5.2 — mandatory)
is_correct_derived  BOOL  <- points > 0   (§5.1)
source_deleted_at · the common timestamps
```

`order` defaults to **0** in the source, so it may repeat. Unstable ordering means "the second
option" changes between runs, which corrupts `payload_hash`, human review, and P2's prompts. **The
two-key `ORDER BY` is mandatory everywhere** and is never abbreviated. (`order` is a reserved word in
MySQL, so: always backticks.)

A/B/C/D letters **do not exist in the source** (§5). If the display needs them, they are synthesized
from `option_index` at render time only — never stored, and never written back to the source.

### `source_media` — from `quiz_files`

```text
source_id · type ENUM(video,image,audio) · path
section_source_id · question_source_id · attach_level ENUM(section,question)
path_unverified BOOL   <- always true in this project
```

**`quiz_files` has no soft delete — deletion there is permanent** (§9 item 5), so a row may point at
a file that no longer exists. Verifying would require access to Production storage, **which is not
available locally**. Decision: the row is copied and flagged `path_unverified = true`, and this limit
is stated on the console. We do not hide what we cannot verify.

Images inside `questions.name` are a **second, independent** media path (§5) — detected via `has_img`
on the question, and both paths are surfaced together on the console.

### `source_results` — behavioural, pseudonymized

```text
source_id · quiz_source_id · total_points
student_ref   CHAR(64)  <- HMAC-SHA256(pepper, user_id).  user_id is never stored
attempt_index INT       <- derived: created_at ordering per (student_ref, quiz_source_id)
source_created_at · source_updated_at · source_deleted_at
duration_estimate_seconds INT  <- updated_at - created_at, explicitly labelled:
                                  a weak approximation (§7.1)
```

`attempt_index` is derived because the source carries no attempt number (§5). And
`duration_estimate_seconds` is stored **labelled as an approximation in the column name itself**, so
it is never later read as a real test duration.

### `source_item_stats` · `source_option_stats` — derived, not mirrored (ADR-022)

`question_result` is never mirrored. Its 13,776,378 answer events are unbounded behavioural data
that nothing in the program annotates individually, so they are read as aggregates and stored as two
derived tables (58,284 + 249,098 rows). `question_result` moved to `profile_tables` on 2026-08-26,
so `assertCopyable()` refuses it by name.

```text
source_item_stats    question_source_id · scope · n · n_correct · p_value
                     m1_corrected · m0_corrected · sd_corrected
                     computed_at · import_run_id · snapshot_id · stats_hash

source_option_stats  question_source_id · option_source_id · scope
                     chosen_n · chosen_share · is_key
                     computed_at · import_run_id · snapshot_id · stats_hash
```

Neither carries the common mirror columns — they are not mirrors of source rows. `scope` is
`active` (attempt not soft-deleted) or `all`; 71% of attempts are soft-deleted, so both are stored
and the choice is P3's. `r_pbis` is not stored: P1 keeps its inputs, P3 computes the coefficient.

### `import_runs` · `import_errors`

```text
import_runs   : id · snapshot_id · kind (profile|bank|behaviour) · started_at · finished_at
                status (running|completed|failed|resumed) · rows_read · rows_inserted
                rows_updated · rows_unchanged · error_count · resume_cursor JSONB

import_errors : id · import_run_id · source_table · source_id · severity (info|warning|error)
                code · message · context JSONB · created_at
```

`resume_cursor` is what makes Phase 7 resumable over 13.8 million rows instead of restarting.
`import_errors` is the constitution's rule in practice: **anomalies are logged, never swallowed**;
a silent `try/catch` is a defect.

---

# 7. Implementation Plan — 10 Phases

Each phase has a goal, steps, files, and a single checkable acceptance criterion. The numbering is
continuous, and the phases are ordered by real dependencies, not by preference.

---

## Phase 1 — The `php artisan lab:profile` Command

**Goal:** run the §6 suite **inside** the three layers, with a recorded, comparable output.

**Steps:**
1. `app/Console/Commands/LabProfile.php` — reads `sql/profiling/*.sql` in numeric order.
2. **A table declaration per file**: a map (query number -> the tables it reads), sourced from the
   table already present in `sql/profiling/README.md`. Before any file executes, every name is passed
   through `SourceReader::assertReadable()`. A single refusal stops the command and names the table.
3. Execution via `DB::connection('injazedu')->select(...)` — layers 1 and 2 apply automatically.
4. Three outputs:
   - A coloured table in the terminal (English — operator output, constitution VI)
   - The full JSON in `profiling_results` inside the `source_snapshots` row — **the authoritative
     record**
   - `docs/reports/p1-profiling.md`, **generated** from that JSON: the summary table with
     `snapshot_taken_at` at the top, then each query with its number and its result. Regenerating
     it overwrites it; nothing in it is hand-maintained.
5. `--query=N` to run a single query, and `--dry-run` to display the declared tables without
   executing.

**Files:** `apps/lab/app/Console/Commands/LabProfile.php` ·
`apps/lab/database/migrations/*_create_source_snapshots_table.php`

**Acceptance criterion:** `lab:profile --dry-run` lists 18 files with their declared tables; and
adding a test file that declares `users` makes the command **fail, naming the table**, before any SQL
is executed.

---

## Phase 2 — Run the Pack and Settle What the Numbers Change

**Goal:** turn eighteen results into the three decisions that change code.

**Steps:**
1. `php artisan lab:profile` in full. Confirm the counts in §2.2 — a mismatch against the same
   snapshot means a bug in the command, not drift in the bank.
2. Read the generated report. **Three findings block downstream code**, because each one changes
   what gets built:

   | Finding | What it decides | Human item |
   |---------|-----------------|------------|
   | The meaning of multi-key (queries 3 + 4) | `answer_key_state` semantics, and therefore `payload_hash` — Phases 4 and 6 | §8 **D** |
   | Enrollment: `course_user` vs `course_order` (queries 15 + 16) | Which table P5 and P6 build on; pinned in the docs | §8 **F** |
   | `correct_count = 0` above 2% (query 3) | The dedup track in P2 stops; the broken-question list becomes the first deliverable, and P1's remaining scope is reconsidered | §8 **E** |

3. Everything else in the pack is **recorded and read, and blocks nothing** — including
   `has_description` (< 30% => the P9 explanation track starts from zero), `has_img_tag`
   (> 10% => the media track is a declared sub-project), and `long_stimulus` (large => §8 is a core
   requirement, not an add-on). These change *later* projects' scope, and they are noted in §11 so
   the change is visible when that project starts.
4. Write an `**Updated**` note in §13 of the governing plan pointing to the report (§3.1 Decision 3).

**Files:** `docs/reports/p1-profiling.md` (generated) ·
`docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (the §13 note only)

**Acceptance criterion:** `profiling_results` holds all eighteen results, the report regenerates from
it identically, and the three findings above have answers. Phases 3, 4 and 5 do **not** wait on this
phase; Phase 6 does, because it derives `answer_key_state`.

---

## Phase 3 — The Mirror Table Schema

**Goal:** fourteen tables in PostgreSQL, owned by Laravel (ADR-013).

**Steps:**
1. One migration per table from §6.2, in the order dependencies allow.
2. The common columns (§6.1) in every table, and a UNIQUE constraint on
   (`source_system`, `source_id`).
3. Indexes only on what downstream projects will actually use — no more (constitution VII: "Indexes
   are earned"):
   `source_questions(section_source_id)` · `source_question_options(question_source_id)` ·
   `source_option_stats(question_source_id)` ·
   `source_results(quiz_source_id)` · `source_results(student_ref)`.
   **No vector index and no trgm index in this project** — there is nothing to index yet.
4. A comment in every migration documenting what was not copied and why (price, Zoom data, `user_id`).

**Files:** `apps/lab/database/migrations/` (14 files)

**Acceptance criterion:** `php artisan migrate:fresh` succeeds, and `NoPiiInLabSchemaTest` passes over
the complete new schema.

---

## Phase 4 — The Derivation Core (deterministic · no database)

**Goal:** that the rules everything downstream depends on are **tested before they touch a single
row**.

**Steps — four functions, each with a unit test:**

1. `AnswerKeyDeriver` (§5.1)
   ```text
   correct_option_ids = [o.id for o in options if o.deleted_at IS NULL and o.points > 0]
   correct_count == 1 -> single_correct
   correct_count == 0 -> broken_no_key      <- escalated, and never used
   correct_count >  1 -> multi_key          <- meaning settled in Phase 2
   ```
2. `OptionIndexDeriver` (§5.2) — ``ORDER BY `order` ASC, id ASC``. A mandatory test on the
   **`order` tie** case (query 5), because that is the case that breaks everything silently.
3. `PayloadHasher` — SHA256 over key-sorted JSON, using the literal definition in §6.2. Test: the
   same input => the same hash; reordering the options in the input does **not** change the hash,
   while changing an option's text does.
4. `StudentRefHasher` — `HMAC-SHA256(pepper, user_id)`. Test: stable for the same input, different
   for a different pepper, **and it throws if the pepper is empty** — a `student_ref` built on an
   empty pepper is not allowed.

**Files:** `apps/lab/app/Support/Derive/` · `apps/lab/tests/Unit/Derive/`

**Acceptance criterion:** all four test suites pass, including the `order` tie case and the empty
pepper case. These are the "Unit tests for the deterministic core" of constitution V item 1.

---

## Phase 5 — The ETL Structure

**Goal:** an idempotent, chunked, resumable import skeleton, **before** any table is imported through
it.

**Steps:**
1. `php artisan lab:import {--kind=bank|behaviour|all} {--resume} {--chunk=}` creates an
   `import_runs` row and links every written row to it.
2. **The upsert**: on (`source_system`, `source_id`). If `payload_hash` matches =>
   `rows_unchanged++` and no write. If it differs => update and `rows_updated++`. New => insert.
   **Re-running against the same snapshot must produce 0 inserts and 0 updates.**
3. **Resume**: `resume_cursor` holds (the table, the last confirmed `source_id`). `--resume`
   continues from there.
4. **The guard at every write site**: `SourceReader::assertCopyable($table)` — no exception, no
   shortcut. This is the second party the contract in
   `specs/004-handover-and-p1-readiness/contracts/source-access-and-stack.md` was written for.
5. `import_errors` receives every anomaly and the batch continues. No silent `try/catch`.
6. Execution over the queue (`database` driver, ADR-011) so heavy batches can run unattended.
7. `--help` carries the operating instructions — what each flag does, what `--resume` picks up, and
   what each error code means. This is the import's documentation; no runbook is written.

**Files:** `apps/lab/app/Console/Commands/LabImport.php` · `apps/lab/app/Jobs/Import/` ·
`apps/lab/app/Support/Import/`

**Acceptance criterion:** running the command twice in a row against a small test set produces, on
the second run, `rows_inserted = 0`, `rows_updated = 0`, and `error_count = 0`; and killing the
process midway then `--resume` continues without duplicating.

---

## Phase 6 — Bank ETL

**Goal:** a faithful copy of the complete question structure.

**The order is mandatory** (key dependencies):

```text
categories -> courses -> chapters -> lectures -> quizzes -> sections
           -> questions -> options -> quiz_files
```

**Steps:**
1. For each table: read through `SourceReader`, derive with the Phase 4 core, upsert with the
   Phase 5 structure.
2. **Soft-deleted rows are copied** along with `source_deleted_at` (§16). They are not excluded at
   copy time, and they are excluded at analysis time — two different decisions in two different
   places.
3. `source_sections`: compute the stimulus fields (`stimulus_length`, `has_stimulus`,
   `is_long_stimulus`, `questions_count`) — the basis of §8.
4. `source_questions`: compute the flags (`has_html`, `has_img`, `is_stem_image_only`,
   `stem_char_length`, `answer_key_state`, `options_count`) and `payload_hash`.
5. `source_question_options`: `option_index` and `is_correct_derived`.
6. `source_media`: both levels (section / question) and `path_unverified = true`.
7. `source_courses`: the permitted columns only — and review confirms that price and the marketing
   fields were not copied.

**Files:** `apps/lab/app/Jobs/Import/Bank/`

**Acceptance criterion:** the copied row count for each table equals the source count recorded in
Phase 2 (including soft-deleted rows), every `source_id` is preserved as-is, and `error_count` equals
the number of anomalies the profiling run predicted — no more and no fewer.

---

## Phase 7 — Behavioural ETL

**Goal:** ~14.9 million rows, pseudonymized, with no interruption that loses work.

**Steps:**
1. `source_results` first (~1.1 million): `student_ref` at read time, then `attempt_index` as a second
   pass inside the database (`ROW_NUMBER() OVER (PARTITION BY student_ref, quiz_source_id ORDER BY
   source_created_at)`) — an order of magnitude cheaper than computing it in PHP.
2. **Revised 2026-08-26 (ADR-022)** — was: `source_answers` (~13.8 million) in `--chunk` batches.
   Now: `ComputeItemStats` and `ComputeOptionStats` push the aggregation into MySQL and store
   `source_item_stats` / `source_option_stats` at the `active` and `all` scope. No answer row is
   copied. Cast to DOUBLE before every ratio — MySQL quantizes to 4 decimal places otherwise.
3. **`user_id` is read, hashed, and discarded in the same statement.** No intermediate variable holds
   it, no log prints it, no column receives it.
4. Record the actual elapsed time in `import_runs` — not a gate, but a number P3 needs to size its
   own batches.

**Files:** `apps/lab/app/Jobs/Import/Behaviour/`

**Acceptance criterion:** the behavioural mirror is complete with no gaps
(`SUM(source_item_stats.n)` and `SUM(source_option_stats.chosen_n)` at the `all` scope each equal
`COUNT(question_result)`, and `COUNT(source_results)` equals its source count), no column carries
`user_id`, and killing the process halfway then `--resume` continues from the next batch rather than
from the beginning.

---

## Phase 8 — Validation Checks

**Goal:** make data problems **visible and classified**, neither hidden nor repaired.

The §16 checks, each with its own code written into `import_errors`:

```text
MISSING_OPTIONS          a question with no options
EMPTY_STEM               the question text is empty after stripping tags
ZERO_CORRECT             answer_key_state = broken_no_key      <- the most serious
MULTI_CORRECT            answer_key_state = multi_key
DUPLICATE_OPTION_TEXT    two options with identical text within a question
OPTION_ORDER_TIE         a tie in the order value within a question  (§5.2)
BROKEN_HTML              unbalanced tags in raw_text
STEM_IMAGE_ONLY          the question is an image with no text
ORPHAN_SECTION           a question pointing at a nonexistent section
ORPHAN_QUIZ              a section pointing at a nonexistent quiz
CATEGORY_ORPHAN_PARENT   parent_id pointing at a nonexistent category  (§9 item 2)
STIMULUS_NO_QUESTIONS    a section with shared text and no questions
QUESTION_NO_SECTION      a question with an empty section_id
```

**Rule:** `BROKEN_HTML` **does not stop the batch** — it is isolated, logged, and the run continues
(§16 Risks). And `ZERO_CORRECT` carries `severity = error`, because it affects a student right now.

The code list lives in one enum with a human-readable description per case; the console and
`lab:import --help` both read it, so there is one source of truth for what a code means.

**Files:** `apps/lab/app/Support/Import/Validators/` · `apps/lab/tests/Unit/Validators/`

**Acceptance criterion:** the `ZERO_CORRECT` rate among active questions matches the result of
query 3 from Phase 2. **The profiling and the mirror must agree** — a discrepancy means one of them
is wrong.

---

## Phase 9 — The Inventory Console (Filament)

**Goal:** that the team sees the bank, rather than reading about it.

**Steps:**
1. An Arabic RTL console (constitution VI). Technical identifiers stay in English.
2. **A fixed header** on every screen: `snapshot_taken_at`, the row count, and the date of the last
   `import_run`.
3. The cards — from §16 and from §11 in v1.0, all sourced from the mirror rather than the source:
   ```text
   Total questions (active / soft-deleted)   Questions by category
   Questions by course                       Questions by quiz
   Option-count distribution                 Answer-key integrity (single/none/multi)
   Questions with no explanation             Questions containing HTML
     (empty description)
   Questions containing images               Sections with shared text (§8)
   Questions needing media review            Import errors by code
   ```
4. **Every number is clickable** (constitution VI) — from the number to the filtered question list to
   the question itself, with its options and its derived answer.
5. An `import_errors` screen filterable by code, severity, and table (§16 acceptance criterion 5).
6. **The suppression rule** wherever any group count is displayed: `n < 10` publishes nothing,
   `n < 30` publishes partially. It applies mainly to P3's screens; it is pinned here as a pattern
   before its numbers exist.

**Files:** `apps/lab/app/Filament/Resources/` · `apps/lab/app/Filament/Pages/`

**Acceptance criterion:** every number on the console leads, in one click, to the rows it was built
from, and no screen shows a number without `snapshot_taken_at` beside it.

---

## Phase 10 — Guards, Tests, and Wrap-Up

**Goal:** that the guarantees are tested properties, not promises in a document.

```text
[ ] NoPiiInLabSchemaTest   <- extended over all fourteen tables. Fails if a column
                              appears carrying user_id, email, phone, name, or id_number
[ ] CopyGuardTest          <- every write site in the ETL passes through assertCopyable(),
                              plus a test proving that copying a profile_tables table throws
[ ] IdempotencyTest        <- two consecutive runs => 0 inserts, 0 updates
[ ] ResumeTest             <- interrupt then --resume duplicates nothing and drops no row
[ ] DerivationTests        <- Phase 4 (four cores)
[ ] ValidatorTests         <- Phase 8 (13 codes)
[ ] StatisticReproducibilityTest <- a sample: a number on the console is reproduced from
                              the raw rows (constitution V: "Every statistical output must
                              be reproducible")
[ ] ReadOnlyGuardTest      <- from P0, stays green with no modification
[ ] lab:health             <- 10/10 after every phase
```

**Wrap-up — the only documents P1 produces**, each because something outside the code needs it:

```text
README.md            a P1 section: two commands and one screen
apps/lab/.env.example  any new key, with no values
docs/runbooks/snapshot.md   provenance updated once (the fixed-copy decision)
CLAUDE.md / AGENTS.md  P1's measured facts, byte-identical in both
```

No runbook, no acceptance record, no handover document (constitution, documentation policy).

**Acceptance criterion:** the whole suite is green, `lab:health` is 10/10, and **removing
`assertCopyable` from any single write site fails a test**. Following the README from a clean Lab
database reaches a populated inventory console.

---

## 7.1 One Spec, One Branch

P1 is delivered as **one Spec Kit feature** covering all ten phases — not four increments
(constitution, "How Work Gets Done"):

```text
/speckit.specify  ->  /speckit.plan  ->  /speckit.tasks  ->  /speckit.implement
feature:  005-p1-profiling-and-question-mirror
branch:   p1/profiling-and-mirror-schema   (already created)
```

The phases above are the implementation order inside that one spec. Phases 3, 4 and 5 do not depend
on Phase 2 and may be built alongside it; Phase 6 does, because it derives `answer_key_state`.

---

# 8. Steps on Your Side (Human)

Items a developer cannot carry out alone — either because they are a decision, an authorization, or
human coordination. **Item B is blocking.**

| # | Item | Why | When |
|---|------|-----|------|
| **B** | 🔴 **Confirm `STUDENT_REF_PEPPER` is set and stored outside Git and off this machine** | In P0 it was just a key in `.env`. In P1 it becomes **irreversible**: once Phase 7 writes ~1.1 million `student_ref` values, changing the pepper orphans every behavioural row with no way to re-link. No restore, no backup (§14.6) | **Before Phase 7**, preferably now |
| **C** | 🟡 **Read the profiling output** once `lab:profile` has run | Not a sign-off. The numbers are the input to items D, E and F below, and they are what §13's estimates get corrected against | End of Phase 2 |
| **D** | **Settle the meaning of `MULTI_KEY`** after seeing the output of queries 3 and 4 | Does the system support more than one correct answer, or are these data-entry errors? (§5.1). The decision is domain expertise: it needs a trainer, not a developer. It fixes `answer_key_state` and therefore `payload_hash` | Phase 2 — **blocks Phase 6**; closes P0's open item 3 |
| **E** | **If broken questions exceed 2%**: who receives the correction list, in what form, and who fixes them in the Production console | §16 makes this **the program's first deliverable** if the condition holds. A question with no correct answer affects a student today | As soon as query 3's result is in |
| **F** | **A decision on `course_user` vs `course_order`** after queries 15 and 16 | Settles an ambiguity in §5 the program cannot advance past, and closes P0's open item 4 | Phase 2 |

**Program-level items — owed before P2, not P1 blockers.** Carried over from P0 §8 and still open:

| # | Item | Why it is on the clock |
|---|------|------------------------|
| **G** | 🟡 Kick off the **taxonomy authoring request** and nominate the subject-matter experts | §20 calls it "the most important scheduling note in the document": the request starts at P2, not at P5, because it costs 2–4 weeks of elapsed time |
| **H** | Book review sessions with the moderators and trainers | §13.3 estimates 30–60 review hours: "scheduled sessions, not review on demand" |
| **J** | Open the **legal question-provenance file** with management | `source_origin` starts entirely as `unknown`; filling it in requires a management decision as evidence (§14.5) |

---

# 9. Deliverables

```text
php artisan lab:profile                 18 queries over the guarded connection
php artisan lab:import                  idempotent · chunked · resumable
source_snapshots.profiling_results      the persisted measurement (JSONB) — authoritative
docs/reports/p1-profiling.md            generated from that JSON, regenerable
An "Updated" note in §13 of the governing plan
14 migrations + 14 populated mirror tables   without a single PII column
A tested derivation core                AnswerKey · OptionIndex · PayloadHash · StudentRef
13 validation codes in import_errors
An Arabic Filament inventory console    every number clickable
A test suite: the 9 items in Phase 10
README.md P1 section · .env.example keys · snapshot.md provenance · CLAUDE.md facts
```

---

# 10. Effort Estimate

| Phase | Days |
|-------|------|
| 1 — The `lab:profile` command | 0.75 |
| 2 — Run the pack and settle the three findings | 0.75 |
| 3 — The mirror table schema | 0.75 |
| 4 — The derivation core | 0.75 |
| 5 — The ETL structure | 1.0 |
| 6 — Bank ETL | 1.5 |
| 7 — Behavioural ETL | 1.0 |
| 8 — Validation checks | 0.75 |
| 9 — The inventory console | 1.5 |
| 10 — Guards, tests, and wrap-up | 0.75 |
| **Total** | **~9.5 days** |

**Why this sits at the top of §16's range (7–10 days):** two pieces of work §16 did not price —
`lab:profile` (Decision 1: a command instead of a direct `mysql` client) and the **full behavioural
mirror** at ~14.9 million rows, resumable (Decision 2). Both are explicitly taken decisions, and both
remove work from P3 that would otherwise have been pushed onto it.

This estimate **excludes** the elapsed time waiting on items D and F, which are decisions, not
working time.

---

# 11. Go / No-Go Thresholds

From §16 and §6.3. The first block changes scope; the second block is **not accepted** at any value.

| Condition | Decision |
|-----------|----------|
| **`correct_count = 0` above 2%** | **The dedup track in P2 stops.** Correcting the broken questions becomes the first deliverable handed to the team, and P1's remaining scope is reconsidered. A question with no correct answer affects a student now |
| `has_description` below 30% | The explanation track in P9 starts from zero, and the assumption of using it as few-shot examples is struck from every estimate. **Does not block P1** |
| Large `long_stimulus` | §8 is promoted from "add-on" to a **core requirement**, and P2's rule ("different text => not a duplicate") becomes mandatory rather than defensive. **Does not block P1** |
| `has_img_tag` above 10% | The media track is a declared sub-project, not an edge case handled implicitly. **Does not block P1** |
| The ETL is not idempotent | **Not accepted** — re-running against the same snapshot must produce zero change |
| Any PII column appears in the Lab database | **Not accepted** — proven by `NoPiiInLabSchemaTest` against the schema |
| Any write from the Lab reaches MySQL | **Not accepted** — the three layers stay green, or the project stops |
| A row from `profile_tables` is copied | **Not accepted** — `assertCopyable()` refuses by name, and a test proves it |
| The broken-question rate differs between the profiling run and the mirror | **Not accepted** until the difference is explained — one of them is wrong |

---

# 12. Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| Running the suite outside the three layers "because it's faster" | Decision 1: `lab:profile` is the approved path, and the declaration fails before any SQL executes |
| **Changing `STUDENT_REF_PEPPER` after the import** | Item B is blocking, and the derivation throws on an empty pepper. No restore fixes this — prevention is the only cure |
| Broken HTML failing a batch | `BROKEN_HTML` is isolated in `import_errors` and the batch continues (§16) |
| An interruption in the 13.8-million-row batch | `resume_cursor` + `--resume`, with an acceptance criterion that tests the interruption in practice, not in theory |
| PII leaking through an ETL bug | The copy allowlist + `assertCopyable` at every write + `NoPiiInLabSchemaTest` against the schema itself |
| `categories.parent_id` orphans silently corrupting the tree | `CATEGORY_ORPHAN_PARENT` is logged and not repaired; the tree is shown incomplete and honest rather than complete and guessed |
| Unstable option ordering corrupting `payload_hash` | The two-key `ORDER BY` is mandatory (§5.2) + a test on the `order` tie case |
| Treating "a question with no answer row" as a skip | A structural limit written into §6.2 and into the generated report: `option_id` is NOT NULL, so a skip creates no row |
| A dead media path read as present | `path_unverified = true` always, and stated on the console |
| A number read out of its frame | `snapshot_taken_at` (2026-08-07) prints on every screen and every report. The date is context, never a gate |
| P1 inflating and absorbing P2/P3's work | §4.2 is explicit, plus the rule "if you are comparing two questions, you are in the wrong project" |
| The profiling result being measured and then not used | Items D, E and F are named decisions with owners, and Phase 6 cannot derive `answer_key_state` until D is answered |

---

# 13. Acceptance Criteria

```text
[ ] lab:profile runs all 18 queries inside the three layers and persists them to
    source_snapshots.profiling_results; docs/reports/p1-profiling.md regenerates from it.
[ ] The three blocking findings (multi-key meaning · enrolment table · broken rate) have
    answers before Phase 6 derives answer_key_state.
[ ] §13 of the governing plan carries an updated note pointing to the report.
[ ] Every question is imported, and the Production identifier is preserved in every row.
[ ] Soft-deleted rows are copied with source_deleted_at, not dropped.
[ ] The import is idempotent: two consecutive runs => 0 inserts and 0 updates.
[ ] The import is resumable: interrupt then --resume duplicates nothing and drops no row.
[ ] assertCopyable() at every write site — and removing it from any one site fails a test.
[ ] No PII column in any of the fourteen tables — proven by NoPiiInLabSchemaTest.
[ ] student_ref is derived via HMAC, and user_id is neither stored nor written to any log.
[ ] Validation errors are visible and filterable by code and severity in the UI.
[ ] The broken-question rate in the mirror equals its rate in the profiling run.
[ ] Every number on the console is clickable through to the questions themselves.
[ ] Every screen displays snapshot_taken_at beside its numbers.
[ ] At least one statistic is reproduced from the raw rows in a test.
[ ] lab:health still passes 10/10, exit 0.
[ ] Not a single row was written to injazedu.co, nor to the local snapshot, by the Lab.
[ ] Not a single row from profile_tables is stored in the Lab database.
```

---

# 14. Handover to P2 and P3

## 14.1 What P2 Inherits Ready

```text
source_questions.raw_text            the original text, unmodified — the basis of P2's three layers
source_question_options              ordered by a stable option_index, with is_correct_derived
source_sections                      stimulus_raw + is_long_stimulus — the basis of §8 and the
                                     blocking rule
answer_key_state                     P2 knows in advance which questions are comparable at all
payload_hash                         literal payload-level duplication, with no LLM and no embedding
The true active question count       top-K and the range thresholds are calibrated against it (§13.2)
pgvector + pg_trgm                   present since P0, and with no indexes yet (constitution VII)
```

**What P2 does not inherit:** no `clean_text`, no `search_text`, no similarity hash, not one vector.
All of it is its own work.

## 14.2 What P3 Inherits Ready

```text
source_results     student_ref · quiz_source_id · total_points · attempt_index
source_item_stats   question · scope · n · n_correct · p_value · m1 · m0 · sd
source_option_stats question · option · scope · chosen_n · chosen_share · is_key
```

**And that is all P3 needs** — no AI, no embeddings, no taxonomy. §18 calls it "the highest
value-per-effort in the program," and P1 turns it into a matter of SQL.

Query 14 (the distribution of answer counts per question) defines **P3's coverage precisely**: how
many questions have `n >= 30`, enough for full statistics; how many have `n >= 10`, enough for
p-value alone; and how many fall below that and are suppressed entirely.

## 14.3 Numbers That Are Not Re-derived

Every number in `source_snapshots.profiling_results` is the reference for downstream projects. A
project that needs a number **reads it from there** (or from the report generated from it), and does
not re-query the source — otherwise each project ends up with its own version of the truth.

---

# 15. Open Items

| # | Item | Impact |
|---|------|--------|
| 1 | **The governing plan ends at §20 (P5)**. §21 (metrics), §34 (ordering), P6–P9 and Phase D are **referenced but unwritten** | Does not block P1. §17 and §19 both defer to "the target in §21" for acceptance gates, so Part 4B must be completed **before P4** |
| 2 | `T_high` and `T_low` are not calibrated | They are calibrated in P2 against the 400-pair set. P1 does not go near them |
| 3 | P2's acceptance criterion (precision >= 0.90 at recall >= 0.70) assumes a sound bank | If the 2% threshold in §11 triggers, the ordering of P2 itself is reconsidered, not its criterion |

**Closed by this plan:** P0 §8 item E (the snapshot refresh policy — fixed at 2026-08-07, no
refresh). **Closed once Phase 2 runs:** the meaning of `MULTI_KEY` (P0 §15 item 3) and the enrollment
ambiguity (P0 §15 item 4).

---

**End of the P1 plan.**
Next project: **P2 — Arabic Normalization & Duplicate Intelligence** (§17), and in parallel
**P3 — Item Statistics** (§18), which needs only two tables from P1.
Neither begins before the acceptance criteria in §13 above are satisfied.
