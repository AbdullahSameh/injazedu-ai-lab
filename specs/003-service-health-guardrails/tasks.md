---
description: "Task list for Service, Health Matrix & Guardrails (P0 — المراحل 6–8)"
---

# Tasks: Service, Health Matrix & Guardrails (P0 — المراحل 6–8)

**Input**: `spec.md`, `plan.md`, `notes.md`, `data-model.md`, `contracts/ai-service.md`

**Tests**: the service's own suite proves the embedding contract; `php artisan test` proves the
guardrails; shell scripts prove infrastructure; `lab:health` is itself an executable test. No coverage
target, no e2e suite, no mocking layer.

**Format**: `[ID] [P?] [Story]` — `[P]` = parallelisable · US1 the health matrix · US2 the service ·
US3 the guardrails · `[OPERATOR]` = needs a human.

---

## Build order ≠ priority order, and that is deliberate

US1 is **P1 in value** and **last in assembly**. Four of its ten checks have no target until the
service exists, and two exist only to prove US3's guarantees. Building it first would mean writing
a command whose checks cannot run.

So the phases below run US2 → US3 → US1. Each is still independently testable at its checkpoint, and
US1 remains the increment's reason for existing — it is simply the thing that gets assembled once its
parts are real.

```text
Phase 1  Foundational — the shared environment surface
Phase 2  US2  the service        (المرحلة 6)   ← the long pole
Phase 3  US3  the guardrails     (المرحلة 8)   ← independent of Phase 2; can run in parallel
Phase 4  US1  the health matrix  (المرحلة 7)   ← needs both  🎯 the outcome
Phase 5  Acceptance
```

---

## Phase 1: Foundational

**Purpose**: the environment keys and configuration both the service and the application read. Blocks
both US2 and US1.

- [X] T001 [P] Add three keys to `apps/lab/.env.example` with **no values**: `AI_SERVICE_URL`,
  `EMBEDDING_CONFIG_VERSION`, `STUDENT_REF_PEPPER`. Delete the header comment block that currently
  reads *"Keys that belong to later phases and must not appear early: STUDENT_REF_PEPPER (المرحلة 8),
  EMBEDDING_CONFIG_VERSION (المرحلة 6)"* — this is that phase. Add a line stating that changing
  `EMBEDDING_CONFIG_VERSION` invalidates every stored vector (FR-005)
- [X] T002 Set `AI_SERVICE_URL=http://127.0.0.1:8001` and
  `EMBEDDING_CONFIG_VERSION=eg300m-qat-q4_0/sim-v1/768/l2norm` in `apps/lab/.env`.
  `STUDENT_REF_PEPPER` stays empty until T017
- [X] T003 [P] Extend `apps/lab/config/lab.php` with two blocks: `embedding` (contract version,
  prefix template, dimension 768, both model tags) and `ai_service` (base URL, timeout). Comment the
  contract block with the §12.2 invalidation rule, as `source_tables` is commented with its own
  (data-model.md §3)

**Checkpoint**: both sides can read the contract string from configuration rather than a literal.

---

## Phase 2: US2 — The service (المرحلة 6)

**Goal**: a stateless service on loopback that is the Lab's only door to the model runtime, with the
embedding contract fixed before a single vector exists.

**Independent test**: `scripts/verify-ai-service.sh` passes with Laravel absent — four endpoints, a
768-dimension unit-norm vector carrying its contract version, and no MySQL credential anywhere.

### Scaffold

- [X] T004 [US2] `cd apps/ai-service && uv init --name injazedu-lab-ai-service` then
  `uv add fastapi uvicorn httpx pydantic pydantic-settings asyncpg`. Commit `pyproject.toml` and
  `uv.lock`. Python 3.13.7, `uv` 0.10.12
- [X] T005 [P] [US2] Write `apps/ai-service/.env.example` (committed, every key, no values) and
  `apps/ai-service/.env` (untracked). Keys: Lab database DSN parts, `OLLAMA_HOST`,
  `EMBEDDING_CONFIG_VERSION`, `SERVICE_HOST`, `SERVICE_PORT`. Its header states that **no MySQL key
  belongs in this file** — every source read goes through Laravel's guarded connection (ADR-013,
  FR-003)

### The contract — before the endpoints that expose it

- [X] T006 [US2] `apps/ai-service/app/config.py` — pydantic-settings model. Fail loudly on a missing
  key rather than defaulting; `EMBEDDING_CONFIG_VERSION` has **no default**, because a silent default
  is exactly how a contract drifts
- [X] T007 [US2] `apps/ai-service/app/embedding.py` — apply the prefix
  `task: sentence similarity | query: {text}` **server-side** (one owner, FR-004); call
  `/api/embed`; read the window from `/api/show` → `gemma3.context_length` and **cache it** rather
  than hard-coding 2048 (notes N2); set `truncated = prompt_eval_count >= context_length`;
  L2-normalize; raise on a zero-norm vector rather than dividing by it (notes N1)
- [X] T008 [P] [US2] `apps/ai-service/app/logging.py` + request middleware — exactly one structured
  JSON line per request (`request_id`, `endpoint`, `method`, `model`, `latency_ms`, `status`, and
  `truncated` on `/embed`), and the matching `X-Request-Id` response header (FR-009)

### Endpoints

- [X] T009 [US2] `apps/ai-service/app/health.py` — three independent probes. The database probe is
  `SELECT 1` plus the server version and **nothing else** (read-only, FR-003). The runtime probe calls
  **chat first, then embedding** — reversing it evicts the embedding runner on this machine
  (notes N5) — with `num_predict: 1` and a per-call `num_ctx: 4096`, never a global setting (FR-008),
  asserting a response came back and never its content
- [X] T010 [US2] `apps/ai-service/app/main.py` — the five endpoints exactly as
  `contracts/ai-service.md` specifies, including the status codes: `503` unreachable, `422` empty
  input, `502` zero-norm. `/health/full` composes the three sections **without masking** any of them
  (FR-002)
- [X] T011 [P] [US2] `apps/ai-service/tests/` — the contract assertions: dimension is 768; L2 norm is
  1 within tolerance (SC-016); the same text sent with and without the prefix produces **different**
  vectors, proving the service applies it; over-length input sets `truncated: true`; a zero-norm
  vector raises rather than returning `NaN`

### Verification

- [X] T012 [US2] `scripts/verify-ai-service.sh` (bash 3.2, sourcing `scripts/lib/output.sh`) — the
  listening socket is inspected **directly** for its bind address, never inferred from configuration;
  a non-loopback connection is refused; all four health endpoints answer; no MySQL key appears in the
  service's environment file; and `EMBEDDING_CONFIG_VERSION` **matches** the value in `apps/lab/.env`,
  the way `verify-lab-app.sh` already asserts the database-password pair (data-model.md §5)
- [X] T013 [US2] Verify the failure modes by hand and record what each returns: model runtime stopped
  (`/health/ollama` → 503 naming the model, `/health` still 200), Lab database stopped
  (`/health/db` → 503), and `/health/full` reporting one section failed while the other two still
  report their own state

**Checkpoint**: the service answers on loopback, the contract is fixed and asserted, and nothing has
been written anywhere.

---

## Phase 3: US3 — The guardrails (المرحلة 8)

**Goal**: the boundaries made executable rather than remembered.

**Independent test**: `php artisan test --filter='ForbiddenTableRefusal|ReadOnlyGuard|SourceTableAllowlist'`
passes with the service and the model runtime both **stopped**.

Independent of Phase 2 — these two phases can run in either order, or in parallel. This is the
smaller one and touches only files that already exist.

- [X] T014 [P] [US3] `apps/lab/tests/Feature/ForbiddenTableRefusalTest.php` — a data provider over
  the seventeen §14.2 names (`users`, `orders`, `course_order`, `book_order`, `coupons`,
  `certificates`, `complaints`, `complaint_responses`, `social_providers`,
  `personal_access_tokens`, `paymob_logs`, `zoom_users`, `audits`, `telescope_entries`,
  `google_oauth_tokens`, `failed_jobs`, `settings`), one assertion each, each asserting the exception
  **names the table it refused** (FR-021, SC-011). Enumerated explicitly so widening the allowlist
  cannot pass silently
- [X] T015 [US3] Re-run `ReadOnlyGuardTest` to confirm each of the three write-blocking layers still
  refuses **alone** after this increment's configuration changes (FR-022, SC-012)
- [X] T016 [US3] Extend `scripts/verify-repo-boundary.sh` with three assertions:
  `apps/ai-service/.env` **is** ignored, `apps/ai-service/.env.example` is **not** (inverted, like the
  two existing templates), and no `.env` file's pepper value appears in tracked content (FR-026)
- [X] T017 [OPERATOR] [US3] Generate the pepper **once** —
  `/opt/homebrew/opt/php@8.4/bin/php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` — put it in
  `apps/lab/.env` only, and back it up off-machine (§8 item F). **Never regenerate it**: doing so
  after P1 has stored `student_ref` values breaks the link between old and new rows irreversibly.
  Nothing in this increment consumes it (FR-023)
- [X] T018 [US3] Assert the pepper's containment: `git grep` for its value returns nothing, and
  `apps/lab/.env.example` lists the key with no value (SC-013)

**Checkpoint**: seventeen tables refused by name, three layers each blocking alone, and a pepper that
exists and is reachable by nothing.

---

## Phase 4: US1 — The health matrix (المرحلة 7) 🎯 the outcome

**Goal**: one command that proves ten connections — two of them by being refused — and exits non-zero
on any deviation.

**Independent test**: run it with the stack up (ten pass, exit 0), then stop one service and run it
again (only the affected checks fail, each naming what it could not reach, exit ≠ 0).

**Depends on**: Phase 2 (checks 2, 4, 5, 6) and Phase 3 (checks 9, 10).

### The probe table

- [X] T019 [US1] Migration `apps/lab/database/migrations/…_create_lab_vector_probes_table.php` —
  `unsignedInteger('id')->primary()` (always 1, idempotent by construction), `vector('embedding', 768)`
  using the framework's **native** column type (notes N4), `timestamp('written_at')->nullable()`.
  Comment it as `lab_job_probes` is commented: fixed id, no column may hold personal data

### The checks — the two inverted ones first

> Written first on purpose. A matrix that reports nine greens and forgets why it exists is the exact
> failure mode this increment guards against.

- [X] T020 [US1] `apps/lab/app/Support/Health/CheckResult.php` — the value object of data-model.md §1:
  `number`, `name`, `target`, `expectation` (`must_succeed` | `must_be_refused`), `outcome`
  (`pass` | `fail` | `skipped`), `detail`. `expectation` is a **field, not a comment**, because checks
  9 and 10 read backwards otherwise
- [X] T021 [P] [US1] Checks **9 and 10** in `apps/lab/app/Support/Health/` — attempt an `INSERT`
  through the guarded source connection, and ask `SourceReader` for `users`. Each passes **only** when
  refused, and each records which mechanism refused it. The write must be attempted through the
  guarded connection alone, so a syntax error or a missing table cannot masquerade as a guard
  (FR-013, SC-003)
- [X] T022 [P] [US1] Checks **1, 3, 7, 8** — Lab database reachable; a job dispatched **and executed**
  by a worker, asserted after that worker has exited (FR-016); a 768-float **deterministically
  generated** vector written and read back **exactly equal**, never a model output (notes N3); and the
  source question count reported **together with** `snapshot_taken_at` (FR-018)
- [X] T023 [P] [US1] Checks **2, 4, 5, 6** — via Laravel's HTTP client (Guzzle 8.0.2 is already
  installed, notes N6). Check 2 asks the service about itself only; 4, 5, and 6 ask it about its
  dependencies. Keep 5 before 6 (notes N5) and **do not** assert both models are simultaneously
  resident — that was `002`'s measurement and it is load-order dependent

### The surfaces

- [X] T024 [US1] `apps/lab/app/Console/Commands/LabHealth.php` — runs all ten in fixed order, renders
  a table showing each check's **expectation** on its own line, and exits non-zero if any check fails
  **or is skipped** (FR-014). English throughout (Constitution VI)
- [X] T025 [US1] Replace the placeholder in `apps/lab/app/Filament/Pages/LabHealth.php` and its view
  with an on-demand run: **no status until the operator presses run**, then the same ten results, with
  a pending state while it works. Persist nothing, add no table, and run nothing on page load
  (FR-019). Measured worst case is a cold stack — ~5 s chat, ~1 s embed (notes N5)
- [X] T026 [US1] Re-run `NoPiiInLabSchemaTest` over the schema **including** `lab_vector_probes`
  (FR-025, SC-015)

### Verification

- [X] T027 [US1] Verify the failure modes by hand and record the output of each: Lab database stopped
  (checks 1, 4, 7 fail naming `postgres:5433`); service stopped (2, 4, 5, 6 fail naming
  `ai-service:8001`); model runtime stopped (5, 6 fail naming the runtime **and** the model tag). In
  every case the remaining checks still report and the exit code is non-zero (SC-002)

  **Observed 2026-08-22**: PostgreSQL stopped → checks 1, 3, 4, and 7 failed, all naming the
  affected database path, exit 1 (check 3 is also affected because the approved queue backend and
  probe table are PostgreSQL-backed); AI service stopped → checks 2, 4, 5, and 6 failed naming
  `ai-service:8001`, exit 1; Ollama stopped → checks 5 and 6 failed naming `ollama:11434` and each
  model tag, exit 1. Every other check continued and reported. All three dependencies were restored,
  then the matrix returned ten passes and exit 0.

**Checkpoint**: ten checks, two of them passing by being refused, and a command that fails honestly.

---

## Phase 5: Acceptance

- [X] T028 Run `quickstart.md` top to bottom on the running stack and correct anything that does not
  behave as written. The quickstart is the increment's own proof, not a summary of it
- [X] T029 Assert the source is untouched: zero rows written to `injazedu` across the whole increment
  (SC-004), confirmed by row counts on the allowlisted tables before and after a full health run
- [X] T030 Assert the service wrote nothing: Lab database row counts unchanged across a full health
  run, excluding `lab_vector_probes` and the queue tables, which Laravel owns (SC-010). This is the
  compensating control for the service using the same Lab credentials rather than its own read-only
  role — a decision deliberately left to the operator (plan.md, Open Questions)
- [X] T031 Update `notes.md` with any measurement that differed from Phase 0 — particularly the real
  cold-stack latency of the on-demand panel run, which the fork table in `plan.md` depends on

---

## Dependencies

```text
Phase 1 ─┬─► Phase 2 (US2, service) ──┐
         │                            ├─► Phase 4 (US1, health matrix) ─► Phase 5
         └─► Phase 3 (US3, guardrails)┘
```

- **Phase 1** blocks everything: both sides read `EMBEDDING_CONFIG_VERSION` from configuration.
- **Phase 2** and **Phase 3** are independent of each other.
- **Phase 4** needs both: checks 2, 4, 5, 6 from Phase 2; checks 9, 10 from Phase 3.
- **T019** (the migration) blocks T022's vector check and T026's schema re-assertion.
- **T017** (the pepper) blocks T018 only. It blocks nothing else, because nothing consumes it.

### Within Phase 2

`T006 → T007 → T009 → T010`. The contract is settled before the endpoints that expose it, so an
endpoint cannot quietly define it. T008 and T011 are parallel to that spine.

### Parallel opportunities

```text
T001 ‖ T003                      configuration, different files
T005 ‖ T008 ‖ T011               inside the service, different modules
Phase 2 ‖ Phase 3                entirely independent
T021 ‖ T022 ‖ T023               ten checks, one class each
```

---

## What is deliberately NOT here

```text
A stack starter script or launchd plist   ← المرحلة 11 (operator decision, 2026-08-22)
A read-only Postgres role for the service ← Principle I: ask, do not decide (plan.md)
An HNSW index on the probe vector         ← indexes are earned (Constitution VII)
Matryoshka truncation to 512              ← needs a recorded measurement first (FR-006)
Any prompt or prompt version              ← P2 owns the prompt registry
An ADR                                    ← nothing here is architectural and expensive to reverse
A README or runbook                       ← المرحلة 11
```

If a task in this list starts to settle a question the operator has not settled, it is not a task —
it is an open question for `plan.md` (Principle I).

---

## Summary

| Phase | Story | Tasks | Delivers |
|---|---|---:|---|
| 1 | — | 3 | The shared contract string and service URL, readable from configuration |
| 2 | US2 (P2) | 10 | The stateless service, the embedding contract, structured logs |
| 3 | US3 (P3) | 5 | Seventeen tables refused by name, three env keys, the pepper |
| 4 | US1 (P1) | 9 | Ten checks, the command, the on-demand panel page |
| 5 | — | 4 | Quickstart proven, source untouched, service proven to write nothing |
| | | **31** | |

One task needs a human: **T017**, generating the pepper. It blocks nothing but itself.
