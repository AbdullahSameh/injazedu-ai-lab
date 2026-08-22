---
description: "Task list for Source Access & Lab Runtime (P0 — المراحل 3–5)"
---

# Tasks: Source Access & Lab Runtime (P0 — المراحل 3–5)

**Input**: `spec.md`, `plan.md`, `notes.md`, `data-model.md`

**Tests**: three shell scripts prove infrastructure; three Pest tests prove application behaviour.
No PHP test framework beyond what Laravel ships with. No coverage target, no e2e suite.

**Format**: `[ID] [P?] [Story]` — `[P]` = parallelisable · US1 source access · US2 application ·
US3 model runtime · `[OPERATOR]` = needs a human.

---

## Phase 0: Prove the one open assumption

- [X] T001 Confirm PHP 8.4 can authenticate as `root` with an empty password over TCP (notes.md N1).
  **Done 2026-08-21 — it works**: `CURRENT_USER() = root@localhost`, plugin `caching_sha2_password`,
  `questions` = 29142, no TLS needed. Phase 3 is unblocked.

---

## Phase 1: Foundational

**⚠️ T002 must precede any committed SQL** — under the current blanket `*.sql` rule, a file is
written, `git add` reports nothing, and it never enters history (notes.md N6).

- [X] T002 Narrow `*.sql` in `.gitignore` — ignore dumps by extension and by location
  (`data/snapshots/**`), un-ignore `sql/` and `infrastructure/`, drop the now-redundant single-file
  exception for `infrastructure/postgres/init.sql`
- [X] T003 Shrink the root `.env` and `.env.example` to what Docker Compose reads
  (`LAB_DB_PASSWORD`). Remove the "keys deliberately ABSENT" block — it names `SNAPSHOT_DB_*`,
  `SNAPSHOT_DB_ROOT_*`, and `PRODUCTION_WRITE_ENABLED`, none of which exist under this architecture
- [X] T004 Extend `scripts/verify-repo-boundary.sh` with two assertions: `apps/lab/.env.example` is
  **not** ignored (inverted, like the root template), and a dump path **is** still ignored after T002

**Checkpoint**: the boundary can hold committed SQL and the environment surface is honest.

> T002–T004 were completed on 2026-08-21 during the process simplification, because narrowing a
> safety boundary without landing its test in the same change is exactly the gap they exist to
> close. `verify-repo-boundary.sh` now runs 16 assertions, 3 of them inverted.

---

## Phase 2: US1 — Source access (المرحلة 3) 🎯 MVP

**Goal**: a connection that reads the eleven allowlisted tables and can harm nothing.
**Independent test**: `scripts/verify-injazedu-access.sh` passes with no application present.

- [X] T005 [US1] Create `scripts/verify-injazedu-access.sh`, sourcing `scripts/lib/output.sh`
  (bash 3.2 only). Assertions: MySQL reachable on `127.0.0.1:3306`; each of the eleven allowlisted
  tables readable and reported individually; `SELECT COUNT(*) FROM questions` = **29142** (FR-007).
  Its header carries the assertion list — there is no separate contract document
- [X] T006 [US1] Verify the script's failure modes by hand: MySQL stopped (exit 2, named), a
  deliberately misspelled table (exit 1, named). **No inverted write check** — `root` can write, and
  asserting otherwise would be a lie. The write guarantee is US2's, at the application layer

**Checkpoint**: the source is reachable and the eleven tables are confirmed present.

---

## Phase 3: US2 — The application (المرحلة 5)

**Goal**: an application owning every Lab migration, running a real queued job, holding both
connections with the source connection guarded three ways — on PHP 8.4, without disturbing the
machine's other 31 projects.

### Install

- [X] T007 [US2] `/opt/homebrew/opt/php@8.4/bin/php $(which composer) create-project laravel/laravel apps/lab "^13.0"`.
  Use the 8.4 binary for **resolution as well as execution** (notes.md N2). Never `brew link`
- [X] T008 [US2] Add and install the panel: `require filament/filament:"^5.0"` then
  `artisan filament:install --panels`, both under the 8.4 binary
- [X] T009 [US2] Populate `apps/lab/.env` and `apps/lab/.env.example` per `data-model.md` §3 —
  `APP_*`, the `DB_*` pgsql group pointing at `127.0.0.1:5433`, `INJAZEDU_DB_*` (`root`, empty
  password), `QUEUE_CONNECTION=database`, the `LOG_*` group, `SNAPSHOT_TAKEN_AT`, `OLLAMA_HOST`.
  The template carries every key with no values (FR-005, FR-020)

### The three guards

- [X] T010 [US2] Configure both connections in `apps/lab/config/database.php` — `pgsql` as the
  default against the Lab database, and `injazedu` with a read host and an **empty write host list**
  (FR-001, guard 1)
- [X] T011 [US2] Add the read-only query listener to `apps/lab/app/Providers/AppServiceProvider.php`
  — throws a `ReadOnlyViolation` on any statement that is not `SELECT`/`SHOW`/`DESCRIBE`/`EXPLAIN`
  on the `injazedu` connection (FR-002, guard 2)
- [X] T012 [US2] Create `apps/lab/config/lab.php` with the eleven `source_tables` and
  `snapshot_taken_at`, and `apps/lab/app/Support/SourceReader.php`, which throws naming any table
  outside the list (FR-003, FR-006, guard 3)
- [X] T013 [US2] Prove the two write-blocking layers block **independently** (SC-003): remove the
  listener and confirm the empty write-host list still refuses; restore it, remove the write-host
  guard, and confirm the listener still throws. If the empty write-host list falls back to the read
  host, record that the listener carries the guarantee alone (notes.md N3)

### Schema, queue, panel, logs

- [X] T014 [US2] Create the `lab_job_probes` migration — fixed id, `dispatched_at`, `ran_at`,
  `worker_pid` (`data-model.md` §6). Laravel owns every Lab migration
- [X] T015 [US2] Write `apps/lab/app/Jobs/LabQueueProbe.php` — upserts the fixed id so re-running
  never accumulates rows, recording `ran_at` and the executing `worker_pid` (notes.md N4)
- [X] T016 [US2] Run `artisan migrate` against the Lab database; confirm the framework defaults plus
  `lab_job_probes` are present (FR-016)
- [X] T017 [US2] Add the `lab` channel to `apps/lab/config/logging.php` — daily files, 14-day
  retention, separate from the framework default, carrying `model_name`, `prompt_version`,
  `latency_ms`, `request_id`. Created empty; المرحلة 6 fills it (FR-019)
- [X] T018 [US2] [OPERATOR] Create the panel's operator account with `artisan make:filament-user`
  and confirm the panel requires authentication
- [X] T019 [US2] Create `apps/lab/app/Filament/Pages/LabHealth.php` and its view as a **stated
  placeholder** naming المرحلة 7 as the owner of its content. **No fabricated status indicator**, and
  **no locale lock-in** — P1's first reviewer screen must bring Arabic + RTL rather than unpick a
  decision made here (FR-018)

### Tests and verification

- [X] T020 [P] [US2] `apps/lab/tests/Feature/ReadOnlyGuardTest.php` — `INSERT`, `UPDATE`, and
  `DELETE` through the `injazedu` connection each throw, and zero rows change (SC-002)
- [X] T021 [P] [US2] `apps/lab/tests/Feature/SourceTableAllowlistTest.php` — each of the eleven
  tables is accepted; a table outside the list throws naming it (SC-004)
- [X] T022 [P] [US2] `apps/lab/tests/Feature/NoPiiInLabSchemaTest.php` — no column in the Lab schema
  is named `email`, `phone`, `mobile`, `id_number`, `national_id`, or `name` on a behavioural table
  (FR-024, SC-005)
- [X] T023 [US2] Create `scripts/verify-lab-app.sh` — the app runs 8.4 **and** the machine still
  links 8.2; migrations applied; the probe row exists with `worker_pid` ≠ the dispatcher's after the
  worker has exited; the root `.env`'s `LAB_DB_PASSWORD` equals `apps/lab/.env`'s `DB_PASSWORD`;
  the panel requires auth. Then delegates to `php artisan test`
- [X] T024 [US2] Re-run `scripts/verify-repo-boundary.sh` and require it to pass now that a
  dependency tree and a build directory exist (FR-021, SC-011)

**Checkpoint**: US1 and US2 both work. The read-only guarantee is a tested property of the
application, not a rule code must remember.

---

## Phase 4: US3 — Model runtime (المرحلة 4)

**Independent of US1 and US2 — may be done at any point, including first.** The 4.1 GB pull is the
long pole.

- [X] T025 [P] [US3] Install Ollama 0.32.15 with the official macOS installer
  (`curl -fsSL https://ollama.com/install.sh | sh`). The app registers as a login item and runs with
  defaults — measure before pinning anything (FR-008, FR-011; operator decision 2026-08-22)
- [X] T026 [P] [US3] `ollama pull embeddinggemma:300m-qat-q4_0` (227.5 MB measured)
- [X] T027 [P] [US3] `ollama pull gemma4:e2b-it-qat` (**4,135.5 MB** measured, including a 941 MB
  vision projector). A tag that cannot be retrieved **stops the increment** — no substitution (FR-010)
- [X] T028 [US3] Create `scripts/verify-model-runtime.sh` — runtime alive; both tags present under
  exact match; the listening socket is loopback-only, with one non-loopback attempt **refused**
  (inverted); no global context length is set (FR-009, FR-012, FR-013)
- [X] T029 [US3] Add the `--with-memory` flag — load both models with empty requests that produce
  neither text nor vectors, read resident memory, compare against the §12.3 line and the 13 GB idle
  ceiling, and **report an overrun as a go/no-go trigger** rather than absorbing it (FR-011,
  FR-014, SC-006)
- [X] T030 [US3] Record the measured figure. **If and only if** it breaches the ceiling, pin
  `OLLAMA_MAX_LOADED_MODELS`, `OLLAMA_NUM_PARALLEL`, and `OLLAMA_KEEP_ALIVE` — and read them back
  off the running process, never off a file (notes.md N5)

**Checkpoint**: all three stories independently functional.

---

## Phase 5: Close out

- [X] T031 [OPERATOR] Reboot the machine, then re-run `verify-model-runtime.sh` and
  `verify-lab-app.sh` — a runtime that works until the machine reboots is a failure worth catching
- [X] T032 Amend `docs/plans/project/1/p0-ai-lab-foundation.md` §12.3's memory table with the figure
  measured in T029, replacing the estimate in the same change that measures it
- [X] T033 Run `quickstart.md` end to end and confirm the five-script chain reaches `INCREMENT GREEN`

---

## Dependencies

- **T001 gated US2 and is discharged** — PHP authenticates. Nothing blocks Phase 3.
- **T002 must precede any committed SQL file.**
- **US1** needs T002. **US2** needs T001, T009, and 001's Lab database; T010–T013 need US1's
  confirmed connection. **US3** needs nothing from either.
- **Phase 5** needs all three stories.

**Parallel opportunities**: T020/T021/T022 are three separate test files. T026 and T027 are both
pulls. **US3 runs in parallel with US1 or US2 entirely** — it shares no file and no dependency.

---

## Implementation Strategy

1. **Start T027's pull early** in the background — 4.1 GB is the longest single wait here.
2. **Phase 1 → US1** gives the MVP: the source is reachable and confirmed.
3. **Add US2** → the read-only guarantee becomes a tested property, three layers deep.
4. **Add US3** → both models resident, measured against the budget.

Where this increment stops: no AI service exists, no vector has been stored, no backup has been
restored, and `README.md` has not been written.
