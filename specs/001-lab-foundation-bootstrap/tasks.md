---
description: "Task list for Lab Foundation Bootstrap (P0 — المراحل 0–2)"
---

# Tasks: Lab Foundation Bootstrap (P0 — المراحل 0–2)

**Input**: Design documents from `/specs/001-lab-foundation-bootstrap/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: No separate test layer is generated, and that is deliberate. The three verification
scripts **are** the executable checks Constitution Principle V requires (FR-027), so they appear as
implementation tasks. Adding a test framework on top would be the "coverage target" the principle
forbids.

**Organization**: Grouped by user story. US1 = safety preflight, US2 = repository boundary,
US3 = Lab data layer.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 / US2 / US3
- **[OPERATOR]**: Requires a human decision or privilege — cannot be done by an agent

---

## ⛔ Blocking Gate

**Measured 2026-08-20: `fdesetup status` → `FileVault is Off.`** The snapshot (57,482 users,
~70K orders, ~24,408 API tokens that may still be live) is on an unencrypted disk right now. This is
an active violation of the constitution's non-waivable data-protection section, not a future task.

- [X] T001 [OPERATOR] Enable FileVault (System Settings → Privacy & Security → FileVault → Turn On) and store the recovery key off this machine
- [X] T002 [OPERATOR] Record the recovery key's storage location and attestation date in `docs/runbooks/safety.md` — the location only, never the key (created by T009)

**T001 does not block building.** Encryption converts in the background, and Phases 1–5 touch no
snapshot data. T001 and T002 block **acceptance** of the increment: until both are done,
`scripts/preflight-check.sh` exits `1` by design and Constitution gate 8 stays failed.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: The directory skeleton every later phase writes into.

- [X] T003 Create the P0 §6 directory skeleton at repository root with `.gitkeep` in each reserved directory: `apps/lab/`, `apps/ai-service/`, `infrastructure/docker/`, `infrastructure/postgres/`, `infrastructure/n8n/`, `data/snapshots/`, `data/fixtures/`, `data/exports/`, `storage/documents/`, `storage/extracted/`, `sql/profiling/`, `evals/`, `prompts/`, `docs/architecture/`, `docs/ADR/`, `docs/runbooks/`, `scripts/`, `scripts/lib/`
- [X] T004 [OPERATOR] Start the OrbStack daemon (`open -a OrbStack`) and confirm `docker ps` responds — done 2026-08-20: daemon started, `docker ps` responding

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The one shared component all three verification scripts depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T005 Create `scripts/lib/output.sh` — shared `ok()`, `fail()`, `warn()`, and `die()` helpers emitting the `[ OK ] <condition> <value>` line format from `contracts/`, plus a failure counter and a final verdict line. English only (FR-028). **Must be bash 3.2 compatible**: no associative arrays, no `mapfile`/`readarray`, no `${var,,}` (research R2)
- [X] T006 Add a header comment block to `scripts/lib/output.sh` stating the bash 3.2 constraint and listing the forbidden constructs, so the next contributor does not reintroduce them

**Checkpoint**: Shared output contract exists — the three stories can now proceed in parallel.

---

## Phase 3: User Story 1 — Make the machine safe to hold the snapshot (Priority: P1) 🎯 MVP

**Goal**: Prove this machine is fit to hold the production snapshot, and refuse to pass when it is not.

**Independent Test**: Run `scripts/preflight-check.sh` on the current machine. It must exit `1` and
name encryption as the blocker (SC-002). After T001/T002, it must exit `0` with all five conditions
reported individually (SC-001).

- [X] T007 [P] [US1] Create `docs/runbooks/snapshot.md` — provenance record with `snapshot_taken_at: 2026-08-07`, the snapshot's physical location outside the repository, the containment rule (never copied in; `data/snapshots/` stays empty), and `refresh_policy: UNDECIDED — P0 §8 Item E, owed before P1` (FR-007, data-model §3)
- [X] T008 [P] [US1] Create `docs/runbooks/safety.md` — recovery-key custody record with `recovery_key_location`, `attested_on`, `attested_by`, each seeded with the literal placeholder `<UNSET — preflight will fail until edited>`, and an in-file warning that this file names a **location** and must never contain key material (FR-006, data-model §2)
- [X] T009 [US1] Create `scripts/preflight-check.sh` — shebang `#!/bin/bash`, source `scripts/lib/output.sh`, argument parsing for `--quiet`, and the exit-code contract `0` pass / `1` blocking failure / `2` cannot-run. Deliberately **no** `--force` flag (contracts/preflight-check.md)
- [X] T010 [US1] Add condition 1 (encryption) to `scripts/preflight-check.sh` — parse `fdesetup status`, pass on `On` or `Encryption in progress`, and treat an unparseable result as `Off` rather than as a pass (FR-002)
- [X] T011 [US1] Add condition 2 (recovery-key custody) to `scripts/preflight-check.sh` — read `docs/runbooks/safety.md`, fail while the placeholder token is present or `attested_on` does not parse as a date (FR-006a)
- [X] T012 [US1] Add condition 3 (sync exposure) to `scripts/preflight-check.sh` — resolve the repo root with `realpath`, then test it against `~/Library/Mobile Documents/`, `~/Library/CloudStorage/`, `~/OneDrive*`, `~/Dropbox*`, and — when iCloud Desktop & Documents sync is active — `~/Desktop` and `~/Documents`. Report the matched root, not a bare boolean (FR-003, research R1)
- [X] T013 [US1] Add condition 4 (free disk) to `scripts/preflight-check.sh` — `df -g /`, fail below the 20 GB threshold, print both measured and threshold values (FR-004)
- [X] T014 [US1] Add condition 5 (container engine) to `scripts/preflight-check.sh` — probe the Docker socket and `docker context ls`, reporting `responding` / `installed but not running` / `not installed` as three distinct outcomes with distinct remediation text (FR-005)
- [X] T015 [US1] Verify `scripts/preflight-check.sh` against all 7 test cases in `contracts/preflight-check.md`, including the two that must reproduce today's machine state: encryption `Off` → exit `1`, and OrbStack stopped → `installed but not running`. Confirm the run completes in under 30 s (SC-001)

**Checkpoint**: US1 is independently functional. The gate exists and correctly refuses this machine.

---

## Phase 4: User Story 2 — Establish a repository that cannot leak (Priority: P2)

**Goal**: Make the committed boundary mechanical rather than remembered.

**Independent Test**: Run `scripts/verify-repo-boundary.sh`. All 13 assertions pass, including the
inverted one where `.env.example` must come back **not** ignored (SC-003).

- [X] T016 [US2] Create `.gitignore` at repository root covering all 10 forbidden categories in data-model §4 — environment files, plain/compressed/binary dumps, PHP and JS dependencies, Python virtualenv, generated storage, application logs, OS noise — plus the `!.env.example` negation and the `data/snapshots/*` rule with a `!.gitkeep` exception (FR-010, FR-012)
- [X] T017 [US2] Untrack the already-committed OS noise file: `git rm --cached .DS_Store`. Do **not** rewrite history — research R8 confirmed no `.env`, dump, or dependency tree was ever committed, so no history surgery is warranted (FR-013, FR-014)
- [X] T018 [P] [US2] Create `.env.example` at repository root with exactly the five keys this increment needs — `LAB_DB_HOST`, `LAB_DB_PORT`, `LAB_DB_DATABASE`, `LAB_DB_USERNAME`, `LAB_DB_PASSWORD` (empty) — plus `SNAPSHOT_TAKEN_AT=2026-08-07`, and a comment block naming the keys deliberately **absent** and their owning phase: no `SNAPSHOT_DB_*` (المرحلة 3), never any `SNAPSHOT_DB_ROOT_*`, no `STUDENT_REF_PEPPER` or `PRODUCTION_WRITE_ENABLED` (المرحلة 8), no `EMBEDDING_CONFIG_VERSION` (المرحلة 6) (data-model §5)
- [X] T019 [US2] Create `scripts/verify-repo-boundary.sh` with assertions 1–11 from `contracts/verify-repo-boundary.md` — one `git check-ignore -v` per category, asserting exit `0` for the ten forbidden categories and exit **`1`** for `.env.example`. Must create and delete no files (research R7)
- [X] T020 [US2] Add assertions 12 and 13 to `scripts/verify-repo-boundary.sh` — `.DS_Store` is no longer tracked (`git ls-files --error-unmatch` must fail), and `data/snapshots/` contains nothing but `.gitkeep` (FR-012, FR-013)
- [X] T021 [US2] Verify `scripts/verify-repo-boundary.sh` against all 6 test cases in `contracts/verify-repo-boundary.md`, including removing the `!.env.example` negation to confirm the inverted assertion actually fires. Confirm `git status` is clean afterwards (SC-003, SC-004)

**Checkpoint**: US1 and US2 both work independently.

---

## Phase 5: User Story 3 — Stand up the Lab's own database (Priority: P3)

**Goal**: The Lab database runs on 5433 under a verified ceiling, offers both capabilities, and survives restarts.

**Independent Test**: `docker compose up -d postgres`, then
`scripts/verify-data-layer.sh --with-restart`. All 7 assertions pass, and `postgresql@14` on 5432 is
undisturbed throughout (SC-005 … SC-010).

**⚠️ Ordering is not cosmetic**: T022 and T023 come before T024 because Constitution Principle II
requires the ADR to exist *before* the code acting on the deviation (research R9).

- [X] T022 [P] [US3] Write `docs/ADR/ADR-018.md` — Lab database on port 5433. State the deviation, the reason (5432 is held by `postgresql@14`, PID 1984, serving other projects on this machine), and the impact. Measured evidence from research.md
- [X] T023 [P] [US3] Write `docs/ADR/ADR-019.md` — OrbStack as container engine instead of Docker Desktop. State the deviation, the reason (pre-installed; P0 §10 names it the lighter alternative; lower VM overhead against the §12.3 budget), and the impact
- [X] T024 [US3] Create `infrastructure/postgres/init.sql` — `CREATE EXTENSION IF NOT EXISTS vector;` and `CREATE EXTENSION IF NOT EXISTS pg_trgm;`, with a header comment stating that this file runs **only** at volume creation and that verification must therefore query the live database, never this file (research R6)
- [X] T025 [US3] Create `docker-compose.yml` at repository root — image `pgvector/pgvector:0.8.6-pg17`, database `injazedu_lab`, user `lab`, password from `${LAB_DB_PASSWORD}` with no default, port mapping `127.0.0.1:5433:5432`, `mem_limit: 1536m`, the four tuning flags from data-model §6, named volume `lab_pgdata`, `init.sql` mounted read-only, and a `healthcheck` using `pg_isready` (FR-016 … FR-022)
- [X] T026 [US3] Create the local `.env` from `.env.example` with a locally generated `LAB_DB_PASSWORD`. Confirm via `git check-ignore -v .env` that it is ignored before writing any value into it (FR-022)
- [X] T027 [US3] Start the service (`docker compose up -d postgres`) and confirm it reaches ready — container healthy **and** a trivial `SELECT 1` succeeds, not merely "container running" (FR-016, research R5)
- [X] T028 [US3] Create `scripts/verify-data-layer.sh` with assertions 1–3 — readiness timed against the 60 s bound, capabilities via `SELECT extname, extversion FROM pg_extension WHERE extname IN ('vector','pg_trgm')` expecting exactly 2 rows, and the published bind address. **All SQL runs in-container** via `docker compose exec -T postgres psql`; the host client is 14 against a server 17 (research R3)
- [X] T029 [US3] Add assertions 4–5 to `scripts/verify-data-layer.sh` — a connection attempt to the host's non-loopback address on 5433 must be **refused** (inverted assertion, SC-010), and `postgresql@14` must still be listening on 5432 (SC-008)
- [X] T030 [US3] Add assertion 6 to `scripts/verify-data-layer.sh` — read the memory limit back **from the running container**, not from `docker-compose.yml`. Report both the limit and current use. A container with no limit set must **fail**, not pass silently (FR-021, research R4)
- [X] T031 [US3] Add assertion 7 and the `--with-restart` flag to `scripts/verify-data-layer.sh` — create the persistence marker table if absent, upsert one row on a fixed id (idempotent, no duplicates on re-run), restart the service, re-read and compare. The row carries no PII and no production-derived data (FR-020, data-model §6b)
- [X] T032 [US3] Record whether `mem_limit` is actually honoured on Compose v5.1.2. If it is **not**, stop and write an ADR recording the measurement and the switch to `deploy.resources.limits.memory` before changing `docker-compose.yml` — the measurement justifies the deviation, not the other way round (research R4, plan Go/No-Go)
- [X] T033 [US3] Verify `scripts/verify-data-layer.sh` against all 9 test cases in `contracts/verify-data-layer.md`, including unsetting `LAB_DB_PASSWORD` to confirm exit `2` with the missing key named and no fallback to an empty password (FR-022)

**Checkpoint**: All three stories independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T034 Run the full `specs/001-lab-foundation-bootstrap/quickstart.md` end to end on this machine and confirm the combined chain reaches `INCREMENT GREEN` — done 2026-08-21: preflight 5/5, boundary 13/13, data layer 7/7, `INCREMENT GREEN`
- [ ] T035 Reboot the machine, then re-run `scripts/verify-data-layer.sh` to confirm the marker row survives a **full machine restart**, not only a service restart (SC-007, second half) — **operator action: reboot required; re-run the script afterwards (no `--with-restart` needed)**
- [X] T036 [P] Confirm SC-004 and SC-012 by inspection — no real data file and no secret value exists in tracked content, and zero rows were read from the production snapshot during the entire increment — done 2026-08-21: tracked content clean, `.env` untracked/ignored, no MySQL/snapshot code path anywhere in the increment
- [X] T037 Update the *Acceptance Gate — Mapping to P0 §13* table in `spec.md` with observed results, and report the increment as **partial P0** with its closed/advanced/vacuous/untouched tally (FR-029) — done 2026-08-21: observed results recorded in spec.md; tally unchanged at 3 closed · 3 advanced · 2 vacuous · 11 untouched
- [ ] T038 [OPERATOR] Begin P0 §8 Item L — set a password on the native MySQL `root` account. Due before المرحلة 3 and it may break other local projects that connect as `root`, so audit their `.env` files first. Started now because it has lead time, not because it belongs to this increment — **audit done 2026-08-21: 31 project `.env` files under `~/Projects` connect as `root` with (presumably) empty password; `root@localhost` uses `caching_sha2_password`. Setting the password will break all 31 until each `.env` receives it — operator decision pending**

---

## Dependencies & Execution Order

### Phase dependencies

- **Blocking Gate (T001–T002)**: blocks *acceptance*, not construction. Start immediately — encryption runs in the background while Phases 1–5 proceed.
- **Setup (Phase 1)**: no dependencies.
- **Foundational (Phase 2)**: depends on T003. **Blocks all three stories.**
- **User stories (Phases 3–5)**: all depend on Phase 2.
- **Polish (Phase 6)**: depends on all three stories.

### Story dependencies

- **US1 (P1)**: independent after Phase 2.
- **US2 (P2)**: independent after Phase 2.
- **US3 (P3)**: **soft dependency on US2** — T026 writes a real `.env` containing a password, so `.gitignore` (T016) must exist first. This is the one place the stories are not fully independent, and it is a safety ordering rather than a functional one. US3's other 11 tasks need nothing from US2.
- T004 (OrbStack running) additionally gates US3's T027 onward, and US1's T015 test case.

### Within each story

- Runbook documents before the script that reads them (T007/T008 → T011).
- ADRs before the code they justify (T022/T023 → T025).
- Script skeleton before conditions (T009 → T010–T014).
- Service running before verification (T027 → T028–T031).

### Parallel opportunities

- T007 and T008 — different files, both US1.
- T018 runs alongside T016/T017 — different file.
- T022 and T023 — two different ADR files.
- With more than one person: after Phase 2, US1 and US2 run fully in parallel; US3 joins once T016 lands.
- T036 is parallel with T034/T035.

---

## Parallel Example: User Story 1

```bash
# Both runbook documents, different files:
Task: "Create docs/runbooks/snapshot.md — provenance record"
Task: "Create docs/runbooks/safety.md — recovery-key custody record"
```

## Parallel Example: User Story 3

```bash
# Both ADRs, different files, and both must land before docker-compose.yml:
Task: "Write docs/ADR/ADR-018.md — Lab database on port 5433"
Task: "Write docs/ADR/ADR-019.md — OrbStack as container engine"
```

---

## Implementation Strategy

### MVP first (User Story 1 only)

1. Phase 1 Setup → Phase 2 Foundational
2. Phase 3 US1
3. **Stop and validate**: the preflight gate must correctly *refuse* this machine while FileVault is Off. A preflight that passes today is broken, not finished.

US1 alone is genuinely shippable: it closes a live exposure by making it visible and blocking on it.

### Incremental delivery

1. Setup + Foundational → shared output contract ready
2. US1 → the safety gate exists (MVP)
3. US2 → the repository can no longer leak
4. US3 → the Lab database runs
5. Phase 6 → verify, reboot-test, report as partial P0

### Where this increment stops

Of P0 §13's 19 acceptance boxes: **3 closed, 3 advanced, 2 vacuous, 11 untouched**. Reporting this
as "P0 complete" would be a constitutional violation. The next increment is المرحلة 3 — read-only
snapshot access and the ADR-020 grant — and T038 starts its prerequisite now.

---

## Constitution Alignment (`.specify/memory/constitution.md` v1.0.0)

- **Principle V — Basic Testing Only.** No test framework, no coverage target, no e2e suite. The
  three verification scripts are the executable checks the principle calls for. The two inverted
  assertions (T019's `.env.example` must **not** be ignored; T029's non-loopback connection must be
  **refused**) are this increment's analogue of the `lab:health` inverted checks — the ones where
  success is failure.
- **Principle II — Traceability.** T022/T023 are sequenced before T025 precisely so no deviation is
  acted on before its ADR exists.
- **Principle III — Scope.** No task creates an application, service, model, snapshot connection,
  grant, or README. T038 is explicitly marked as belonging to the *next* increment.
- **Principle IV — Loud failures.** T030 fails on an unset memory limit rather than passing; T010
  treats an unparseable encryption state as `Off`; T017 does not rewrite history it has no evidence
  needs rewriting.
- **Principle VII — Measured budget.** T030 and T032 measure the ceiling rather than trusting it.
- **Data Protection (non-waivable).** No task touches the production snapshot. The persistence
  marker row (T031) carries no PII. T001/T002 gate acceptance on encryption and key custody.
