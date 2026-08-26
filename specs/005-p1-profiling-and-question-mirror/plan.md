# Implementation Plan: P1 — Production Profiling & Question Mirror

**Branch**: `p1/profiling-and-mirror-schema` · **Date**: 2026-08-25 · **Spec**: [spec.md](./spec.md)
**Phase 0 findings**: [notes.md](./notes.md) — eight, three of which change the design
**Design**: [data-model.md](./data-model.md) · [contracts/profiling-results.md](./contracts/profiling-results.md)
**Project plan**: `docs/plans/project/1/p1-production-profiling-and-question-mirror.md` (v2.0) — ten
phases, delivered as this one spec (§7.1)

## Summary

Measure the bank, mirror it faithfully, make it visible. Two commands and one console:
`lab:profile` runs the eighteen §6 queries **inside the three read-only layers** and persists the
result as data rather than prose; `lab:import` builds fourteen tables idempotently and resumably,
converting `user_id` to `student_ref` at the moment of reading; a Filament console makes every
number clickable down to the questions. Nothing is cleaned, normalized, or judged — that is P2.

Four things shape the approach:

1. **Phase 0 found a blocker in P0's own code.** Guard 2 takes the first token of a statement, and
   every file in `sql/profiling/` opens with a `--` header — so handing a file's contents to
   `DB::select()` throws `ReadOnlyViolation` on a pure `SELECT` (N1). `lab:profile` strips leading
   comments before executing. Without this the command fails on file 1, and the failure looks like a
   security violation rather than a parser detail.
2. **The no-PII test is wrong in both directions and must be rewritten, not extended** (N2). It
   forbids the column name `name` everywhere — which four mirror tables need — and it does not check
   `user_id` at all, the one column FR-011 exists for. See **Open Question 1**: relaxing `name`
   touches a security assertion, even to match the docblock's own stated intent.
3. **The three clarified decisions removed the two traps this feature was most likely to fall into.**
   A no-op re-import would have logged zero errors and made the console report a clean bank; and
   "multi-key blocks Phase 6" read literally would have idled ~14.9 M rows waiting on a meeting.
   Both are now settled in the spec, and the shape of the work follows from them.
4. **The mirror schema is the expensive-to-reverse artefact.** Everything else here is a command, a
   job class, or a Filament resource — reversible by an edit. `data-model.md` exists for that one
   reason and is pinned against the measured schema, not the plan's prose.

## Technical Context

**Language/Version**: PHP **8.4.2** at `/opt/homebrew/opt/php@8.4/bin/php`, never linked · SQL
(MySQL 9.1 dialect for the pack, PostgreSQL 17 for the mirror and the `attempt_index` window)
**Primary dependencies**: none new. Laravel 13.26.1, Filament 5.7.6, the existing `SourceReader`,
`ReadOnlyViolation`, `SourceTableNotAllowed`, `HealthMatrix`
**Storage**: PostgreSQL 17 + pgvector on `127.0.0.1:5433` — **+14 tables, ~1–2 GB** on top of
today's 8,398 kB · MySQL 9.1.0 on `127.0.0.1:3306`, read-only by application, snapshot fixed
2026-08-07
**Testing**: `php artisan test` for derivations, guards, validators, idempotency, resume and
reproducibility; `php artisan lab:health` as the acceptance instrument — **10/10, exit 0, 7.058 s
cold** (N8) after every phase
**Target platform**: macOS 26.5.2 (Darwin 25.5.0), Apple M1 Pro, 16 GB
**Scale**: 29,142 questions · 124,549 options · 3,362 quizzes · 231 courses · ~1,136,204 results ·
~13,776,378 answers. Chunk size starts at 10,000 rows and is tuned by measurement
**Constraints**: zero rows written to `injazedu` · no `user_id` in any column, log, or error context
· no row from `profile_tables` stored · no vector or trigram index · no LLM call · no gate on the
snapshot's age or on a memory number

## Checks Before Building

- [x] **Nothing decided that needed approval** — five questions were put to the operator on
      2026-08-25 and answered (spec `## Clarifications`). One further item needs a decision before it
      is built and is **not** resolved here: **Open Question 1** below. Everything else was ordinary
      architecture, decided with judgement and stated in this plan.
- [x] **Read-only toward InjazEdu MySQL** — guards 1 and 2 are untouched. `lab:profile` reads through
      the guarded connection and executes only `SELECT`; the ETL calls `assertCopyable()` at every
      write site. Zero rows written, in either direction.
- [x] **No PII into the Lab** — `student_ref` is derived at read time and `user_id` is discarded in
      the same statement. The schema assertion is the backstop and is **strengthened** here: it gains
      `user_id` and four name-shaped columns it never checked (N2).
- [x] **Laravel owns migrations** — fourteen, in dependency order (ADR-013). All derivation is
      deterministic PHP and SQL. **No AI in this feature at all**, so the structured-output rule has
      nothing to bind to.
- [x] **Tests are the targeted kind** — four derivation units, thirteen validator units, the four
      guardrail suites, idempotency, resume, and one statistic reproduced from raw rows. No coverage
      target, no UI wiring tests.
- [x] **Fits the budget** — no model is loaded by this feature. The cost is I/O over ~14.9 M rows,
      bounded by `--chunk` and resumable; storage is ~1–2 GB against 149 GB free.

## One Fork Worth Planning For

| Condition | Decision |
|---|---|
| **`correct_count = 0` exceeds 2%** (query 3, end of the profiling run) | **Stop and re-scope before building the ETL.** The broken-question list becomes the program's first deliverable, P2's dedup track stops, and P1's remaining scope is reconsidered — with the operator, not unilaterally. This is the one result that can invalidate the rest of this plan, and it is known within the first day. |

Everything else the pack returns is recorded and read, and changes only later projects' scope.

## Project Structure

`✅` created here · `✏️` amended · `📁` untouched

```text
injazedu-ai-lab/
├── apps/lab/
│   ├── app/Console/Commands/
│   │   ├── LabProfile.php                      ✅ 18 queries, --dry-run --query=N
│   │   ├── LabImport.php                       ✅ --kind --resume --chunk --queue
│   │   └── LabHealth.php                       📁 the acceptance instrument, unchanged
│   ├── app/Support/
│   │   ├── SourceReader.php                    📁 the only read path, unchanged
│   │   ├── Profiling/                          ✅ file discovery, header parsing, report generation
│   │   ├── Derive/                             ✅ AnswerKey · OptionIndex · PayloadHash · StudentRef
│   │   └── Import/
│   │       ├── Upsert + ResumeCursor           ✅ shared by inline and queued paths
│   │       ├── ImportErrorCode.php             ✅ one enum, 13 cases, one description each
│   │       └── Validators/                     ✅ 13 checks
│   ├── app/Jobs/Import/{Bank,Behaviour}/       ✅ one job per table + the two backfill passes
│   ├── app/Models/Source*.php · Import*.php    ✅ 14 models
│   ├── app/Filament/
│   │   ├── Resources/                          ✅ questions · options · errors · quizzes · sections
│   │   ├── Pages/Inventory.php                 ✅ the cards, every number a link
│   │   ├── Pages/LabHealth.php                 📁 English operator output, inside an RTL shell
│   │   └── Widgets/SnapshotHeader.php          ✅ snapshot_taken_at on every screen
│   ├── app/Providers/Filament/AdminPanelProvider.php  ✏️ Arabic + RTL globally
│   ├── database/migrations/                    ✅ 14 files, dependency order
│   ├── config/lab.php                          ✏️ chunk default, report path
│   ├── lang/ar/                                ✅ the console's Arabic strings
│   └── tests/
│       ├── Unit/Derive/ · Unit/Validators/     ✅
│       ├── Feature/NoPiiInLabSchemaTest.php    ✏️ rewritten — see Open Question 1
│       ├── Feature/CopyGuardTest.php           ✅ every write site + profile-table refusal
│       ├── Feature/ImportIdempotencyTest.php   ✅
│       ├── Feature/ImportResumeTest.php        ✅
│       ├── Feature/ProfileDeclarationTest.php  ✅ headers match README; a forbidden name fails first
│       └── Feature/StatisticReproducibilityTest.php ✅
├── sql/profiling/                              📁 the eighteen files, executed for the first time
├── docs/reports/p1-profiling.md                ✅ GENERATED — never hand-edited
├── docs/plans/core/final_…_full_plan.md        ✏️ §13 gains an "Updated" note only
├── docs/runbooks/snapshot.md                   ✏️ the fixed-copy decision, once
├── README.md                                   ✏️ a P1 section: two commands, one screen
├── apps/lab/.env.example                       ✏️ any new key, no values
└── CLAUDE.md · AGENTS.md                       ✏️ P1's measured facts, byte-identical in both
```

**Structure notes.** `docs/reports/` is a new directory holding one **generated** file — it is
regenerated from `profiling_results`, and nothing in it is hand-maintained (FR-005). `lang/ar/`
is new because the panel becomes Arabic. **No new ADR**: a command, a job class, an enum and a panel
setting are none of them architectural *and* durable *and* expensive to reverse. The one thing that
is — the mirror schema shape — is pinned in `data-model.md`, which is where it belongs.

## Design Artefacts

| Artefact | Why it earns its place | |
|---|---|---|
| [notes.md](./notes.md) | Eight findings from reading P0's code and the measured schema. N1 (guard 2 rejects the query files) and N2 (the no-PII test is wrong in both directions) each change what gets written, and N3–N7 stop a migration from being written against the plan's prose instead of the schema. | ✅ |
| [data-model.md](./data-model.md) | Fourteen tables, checked column-by-column against the schema. The one artefact the constitution says is expensive to reverse once data exists. | ✅ |
| [contracts/profiling-results.md](./contracts/profiling-results.md) | **P2, P3, P4, P5 and P9 are the second party.** §14.3 forbids them to re-query the source, so the JSON they read instead needs a fixed shape — decided now, before there is data in it. | ✅ |
| ~~quickstart.md~~ | Skipped: `README.md`'s P1 section **is** the quickstart and is a deliverable (FR-059). A second copy would drift. | ❌ |
| ~~a new runbook / ADR / acceptance record~~ | Skipped by policy. `lab:import --help` carries the operating instructions (FR-030); that is the import's documentation. | ❌ |

## Implementation Grouping

| Group | Covers | Depends on | Touches the source? |
|---|---|---|---|
| **A — Profiling** | `source_snapshots` migration · `LabProfile` · header parsing · report generation · declaration test | Nothing | **Reads all 17 allowlisted tables.** Writes nothing |
| **B — Schema** | 13 remaining migrations · 14 models · the rewritten no-PII test | Nothing (parallel with A) | No |
| **C — Derivation core** | `AnswerKeyDeriver` · `OptionIndexDeriver` · `PayloadHasher` · `StudentRefHasher` + units | Nothing (parallel with A, B) | No |
| **D — ETL structure** | `LabImport` · upsert · resume cursor · `ImportErrorCode` · idempotency and resume tests | B, C | No (skeleton only) |
| **E — Bank ETL** | 9 table jobs in dependency order · stimulus and question flags · the 13 validators | D, **and A's run** for the count check | Reads the 11 copyable tables |
| **F — Behavioural ETL** | `source_results` → `attempt_index` window → `source_answers` chunked | E · **the pepper (item B)** | Reads `results`, `question_result` |
| **G — Backfills** | `answer_key_state` (after FR-061) · `questions_count` · `requires_media_review` | E · **the multi-key decision** for the first | No |
| **H — Console** | Panel to Arabic/RTL · resources · inventory cards · errors screen · snapshot header | E (F for the answer-count cards) | No |
| **I — Guards & wrap-up** | Copy guard · reproducibility · README · §13 note · CLAUDE.md/AGENTS.md | All | No |

**Order**: A, B and C are independent and come first — A because its output re-scopes everything if
query 3 is bad, B and C because D cannot be built without them. Then D, then E, then F and H in
parallel, with G landing whenever its decision does. I last.

**Within E**: `categories → courses → chapters → lectures → quizzes → sections → questions →
options → quiz_files` is mandatory — it is key dependency order, not preference.

**The pepper gates F, not the whole feature.** A through E and H can be built and tested before it
is confirmed; F cannot start, because once ~1.1 M `student_ref` values exist, changing the pepper
orphans every one of them and there is no backup.

## Decisions Taken Under Principle I

Ordinary architecture, decided with judgement and stated here rather than asked (constitution I,
narrowed 2026-08-25). All are reversible by an edit.

| Decision | Reasoning |
|---|---|
| **`lab:profile` parses the `-- Tables read :` header from each `.sql` file**, and a test asserts the eighteen headers match `sql/profiling/README.md` | The declaration then travels with the query, so a new file cannot exist without one — the property FR-002 protects. A missing or unparseable header is a hard failure, never a default-to-empty (N5) |
| **Comment stripping happens in the command, not in the files** | The files are verbatim §6 and must stay that way; a "cleaned up" query silently answers a different question. The parser adapts to the files, not the reverse (N1) |
| **`sorte_order → sort_order` is a per-table mapping** | Four tables carry the typo, `quizzes` does not. A blanket rename would silently produce a NULL column on `source_quizzes` (N4) |
| **One shared job class per table, driven inline or from the queue** | Two implementations means only one of them is the tested one. `--queue` changes the dispatcher, never the work |
| **`attempt_index` is a SQL window function in Postgres, run as a second pass** | An order of magnitude cheaper than 1.1 M PHP iterations, and it belongs to the mirror, not to the read |
| **Three backfill passes share one idempotent second-pass pattern** — `answer_key_state`, `questions_count`, `requires_media_review` | All three depend on tables that do not exist yet when their own row is written. One pattern, tested once |
| **`import_runs.ran_via` and `elapsed_seconds` are recorded** | P3 sizes its batches from them, and the inline/queue equivalence claim needs evidence, not assertion |
| **The console's quality cards read mirror columns; only the errors screen reads `import_errors`** | Follows directly from the append-only clarification — it is the mechanism that stops a no-op re-import from reporting a clean bank |

## Open Questions

> Per Principle I: the problem, the options, the trade-off, and a recommendation — then stop.

| Question | Options | Recommendation |
|---|---|---|
| **`NoPiiInLabSchemaTest` forbids the column name `name` on every non-framework table.** Four mirror tables need it — `source_categories.name`, `source_courses.name`, `source_quizzes.name`, `source_sections.name` — so the test fails the moment the mirror exists. Its own docblock already states the narrower intent (*"or name on a **behavioural** table"*), but the code never expressed it. Changing a security assertion is a Principle I matter even when it is aligning code with its stated intent. | **(a)** Split the list: hard-forbid `user_id`, `email`, `phone`, `mobile`, `id_number`, `national_id`, `username`, `first_name`, `last_name`, `full_name` on **every** non-framework table, and forbid `name` on **behavioural** tables only (`source_results`, `source_answers`). **(b)** Keep `name` forbidden everywhere and rename the mirror columns to `title` — diverging from both the source and the P1 plan's §6.2. **(c)** Keep the test exactly as delivered and add the four mirror tables to its exemption list — which would also exempt them from `email` and `phone`. | **(a).** It is strictly stronger than what exists today: it **adds `user_id`**, the column FR-011 is actually about and which the test does not currently check at all, plus four name-shaped columns. The only relaxation is `name` on four metadata tables holding category and quiz titles — and a category's title is not a person. **(c)** is the genuinely dangerous option: it would blind the test to `email` and `phone` on exactly the tables the ETL writes most. |

**Operator Decision (2026-08-26): Approved option (a).**

Allow `name` on content/metadata mirror tables such as `source_categories`,
`source_courses`, `source_quizzes`, and `source_sections`.

Hard-forbid `user_id`, `email`, `phone`, `mobile`, `id_number`, `national_id`,
`username`, `first_name`, `last_name`, and `full_name` on every non-framework Lab table.

Also forbid `name` on behavioural tables only: `source_results` and `source_answers`.

**Blocking prerequisite, not a question**: `STUDENT_REF_PEPPER` confirmed stored outside Git **and
off this machine** before group F runs. Already decided (spec Dependencies, P1 plan §8 item B) — it
needs doing, not deciding.

**Decisions owed by others, from group A's output**: the meaning of multi-key (gates group G), the
enrolment table, and the broken-question rate. All three are outputs of the profiling run, which is
why A comes first.
