# Implementation Plan: Lab Foundation Bootstrap (P0 — المراحل 0–2)

**Branch**: `001-lab-foundation-bootstrap` | **Date**: 2026-08-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-lab-foundation-bootstrap/spec.md`

## Summary

Deliver the first three phases of P0: prove the machine is safe to hold the production snapshot,
enforce the repository's committed boundary mechanically, and stand up the Lab database on port 5433
with vector and trigram capability under a declared memory ceiling.

The technical approach is deliberately thin — three standalone bash scripts, one compose file, one
init SQL file, two ADRs, two runbooks, and a directory skeleton. No application, service, or model
layer exists yet, and none is created here (FR-026). The scripts are the tests: Constitution
Principle V requires health checks to be executable rather than documented, and these three cover
concerns (`disk encryption`, `sync exposure`, `ignore boundary`) that the later consolidated
`lab:health` command will never check, so they are permanent rather than scaffolding (FR-027).

**Implementation is currently gated.** FileVault measured `Off` on 2026-08-20. The snapshot —
57,482 user rows, ~70K orders, ~24,408 API tokens — is already on that unencrypted disk. Everything
in Group A below can be built, but the increment cannot be accepted, and no task that touches the
snapshot may run, until the operator remediates. See *Blocking Prerequisite*.

## Technical Context

**Language/Version**: bash **3.2.57** — the only bash on the machine; no bash 4+ syntax (research R2)
**Primary Dependencies**: `git` 2.45.2 · Docker CLI + Compose **v5.1.2** via OrbStack · macOS built-ins (`fdesetup`, `lsof`, `df`, `realpath`). No new dependency is installed.
**Storage**: PostgreSQL 17 + pgvector 0.8.6 (`pgvector/pgvector:0.8.6-pg17`) in a container on `127.0.0.1:5433`, named volume `lab_pgdata`
**Testing**: The three verification scripts themselves, asserting via exit codes. No test framework is added (Constitution V — basic testing only).
**Target Platform**: macOS (Darwin 25.5.0), Apple M1 Pro, 16 GB
**Project Type**: Infrastructure + CLI tooling — no application code
**Performance Goals**: preflight completes < 30 s (SC-001); database stopped → ready < 60 s on a warm volume (SC-005)
**Constraints**: database ≤ 1536 MB and *verified* to be so (research R4) · loopback-only binding · zero reads of the production snapshot (FR-025, SC-012) · must not disturb `postgresql@14` on 5432 (PID 1984)
**Scale/Scope**: one developer machine · 3 scripts · 1 compose file · 1 init.sql · 2 ADRs · 2 runbooks · ~15 directories · 30 FRs / 12 SCs

## Blocking Prerequisite

| Item | Measured state | Effect |
|---|---|---|
| **FileVault** (§8 Item A) | **`FileVault is Off.`** (2026-08-20) | The non-waivable rule — *"the local production snapshot MUST live on FileVault-encrypted storage"* — is **currently being violated**, because the snapshot is already present on this disk. Gate 8 of the Constitution Check cannot pass until encryption is at least converting **and** the recovery key is attested off-machine (FR-006/FR-006a). |

This is a state of the machine, not a defect in the design — indeed the feature exists to detect it.
Planning proceeds; implementation stops at the gate. Group A tasks build the detector, Group B and C
may proceed (they touch no snapshot data), but **acceptance of the increment is blocked** until
`fdesetup status` reports `On` or `Encryption in progress`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

*Source: `.specify/memory/constitution.md` v1.0.0.*

| # | Gate | Principle | Status |
|---|------|-----------|--------|
| 1 | Feature names the process it improves and which layer does each part; no AI action outside suggest-classify-rank-flag-draft-explain; no source question deleted | I | **N/A** — no AI component and no question data in this increment; FR-026 bars business logic. Every deliverable is deterministic shell or declarative config. |
| 2 | Spec cites the governing plan section; schema facts traced; deviations have numbered ADRs | II | **PASS (conditional)** — spec cites P0 المراحل 0–2 and §15. No schema fact is used (nothing is read). **Condition:** ADR-018 and ADR-019 are acted on by المرحلة 2, so per Principle II they must be written *before* that code — research R9 pulls them into this increment rather than المرحلة 11. |
| 3 | Exactly one active project; dependencies accepted; no other project's scope; Go/No-Go stated | III | **PASS** — P0 only, phases 0–2, with an explicit out-of-scope table mapping each excluded item to its owning phase. Go/No-Go limits stated below. |
| 4 | Laravel owns migrations; deterministic metrics; schema-validated AI output; versioned prompts; idempotent jobs; anomalies recorded | IV | **PASS (mostly N/A)** — no migrations, no AI, no jobs exist yet. The applicable clauses hold: secrets only in `.env` with `.env.example` listing every key (FR-022), and no service added beyond what is justified today — no Redis, no n8n. |
| 5 | Tests limited to deterministic unit tests, `lab:health` checks, guardrail/PII tests, golden evals | V | **PASS** — exactly three verification scripts, no framework, no coverage target, no e2e suite. FR-027 keeps them from being duplicated later. |
| 6 | Arabic RTL; `n` and `snapshot_taken_at` with every metric; suppression thresholds; AI labelled; human override | VI | **N/A** — this increment creates no reviewer or student surface. FR-028 scopes operator output to English precisely so Arabic stays reserved for the surfaces the principle governs. `snapshot_taken_at` is *recorded* here (FR-007) for the later reports that must display it. |
| 7 | Fits ~11–13 GB; LLM calls banded and capped; cheap layers first; benchmarked model choices; resumable batches | VII | **PASS** — no LLM and no batch. The one applicable clause is the memory budget: 1536 MB declared, and research R4 requires it be *verified from the running container* rather than assumed from the compose key. |
| 8 | **Non-waivable:** Production read-only; `lab_ro` limited to 11 tables; no PII in Lab DB; `PRODUCTION_WRITE_ENABLED=false`; snapshot handling and backup rules honoured | Data Protection | **BLOCKED — see Blocking Prerequisite.** Production read-only: **PASS** (FR-025/SC-012 — zero reads). `lab_ro` and `PRODUCTION_WRITE_ENABLED`: **N/A**, owned by المرحلة 3 and 8. No PII in Lab DB: **PASS** — the Lab DB holds only a marker row. Snapshot on encrypted storage: **FAIL until remediated** — FileVault is Off today. |

**Post-Phase-1 re-check**: gates 1, 3, 4, 5, 6, 7 unchanged. Gate 2's condition is discharged by
design — ADR-018 and ADR-019 are now deliverables in Group C, sequenced *before* the compose file.
Gate 8 remains blocked on an operator action that no design change can resolve.

### Go / No-Go limits for this increment

| Condition | Decision |
|---|---|
| `fdesetup status` still `Off` at acceptance | **Not accepted.** Non-waivable; no negotiation. |
| Repository resolves inside a cloud-sync root and cannot be relocated | **Not accepted.** Either relocate or disable sync, then re-verify. |
| Port 5433 occupied and cannot be freed | **Not accepted** at 5433 — requires a new ADR superseding ADR-018 before any other port is used. |
| `mem_limit` measurably not honoured on Compose v5.1.2 | Deviation permitted **only** with an ADR recording the measurement and the switch to `deploy.resources.limits.memory` (research R4). |
| Container database cannot hold the ceiling | Fall back to Homebrew `postgresql@17` + pgvector per P0 §11, and record the decision. |

## Project Structure

### Documentation (this feature)

```text
specs/001-lab-foundation-bootstrap/
├── plan.md              # This file
├── spec.md              # Feature specification (30 FRs, 12 SCs)
├── research.md          # Phase 0 output — 9 findings, all measured 2026-08-20
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output — CLI contracts for the three scripts
│   ├── preflight-check.md
│   ├── verify-repo-boundary.md
│   └── verify-data-layer.md
├── checklists/
│   └── requirements.md  # Spec quality + clarification record
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

Created by this increment. `✅` = content delivered here; `📁` = directory only, reserved for a later
phase or project (P0 §6).

```text
injazedu-ai-lab/
├── apps/
│   ├── lab/                          📁 المرحلة 5
│   └── ai-service/                   📁 المرحلة 6
├── infrastructure/
│   ├── docker/                       📁
│   ├── postgres/
│   │   └── init.sql                  ✅ vector + pg_trgm
│   └── n8n/                          📁 P6
├── data/
│   ├── snapshots/                    ✅ stays empty forever — .gitkeep + ignore rule
│   ├── fixtures/                     📁
│   └── exports/                      📁 P9
├── storage/
│   ├── documents/                    📁 P8
│   └── extracted/                    📁 P8
├── sql/profiling/                    📁 المرحلة 11
├── evals/                            📁 P2+
├── prompts/                          📁 P2+
├── docs/
│   ├── plans/                        (exists)
│   ├── schema/                       (exists)
│   ├── architecture/                 📁
│   ├── ADR/
│   │   ├── ADR-018.md                ✅ Lab database on port 5433
│   │   └── ADR-019.md                ✅ OrbStack as container engine
│   └── runbooks/
│       ├── safety.md                 ✅ recovery-key custody record (FR-006)
│       └── snapshot.md               ✅ snapshot provenance record (FR-007)
├── scripts/
│   ├── preflight-check.sh            ✅ FR-001…FR-008
│   ├── verify-repo-boundary.sh       ✅ FR-015
│   └── verify-data-layer.sh          ✅ FR-023
├── docker-compose.yml                ✅ FR-016…FR-022
├── .env.example                      ✅ committed, no values
├── .gitignore                        ✅ FR-010
└── README.md                         📁 المرحلة 11 — not written here
```

**Structure Decision**: The repository root layout is fixed by P0 §6 and is not a choice this plan
makes. Two deliberate departures from the plan's phase scheduling, both justified above: the two
ADRs move from المرحلة 11 into this increment (research R9, Principle II), and `docs/runbooks/safety.md`
is added to hold the recovery-key custody record that clarification Q3 introduced. `README.md` stays
in المرحلة 11 — its acceptance criterion is that following it end-to-end reaches a green `lab:health`,
which cannot exist yet.

### Implementation grouping

| Group | Covers | Depends on | Snapshot-safe? |
|---|---|---|---|
| **A — Safety** | `preflight-check.sh`, `docs/runbooks/safety.md`, `docs/runbooks/snapshot.md` | nothing | Builds the gate |
| **B — Repository** | directory skeleton, `.gitignore`, `.env.example`, untrack `.DS_Store`, `verify-repo-boundary.sh` | nothing | Yes — touches no data |
| **C — Data layer** | `ADR-018`, `ADR-019`, `docker-compose.yml`, `infrastructure/postgres/init.sql`, `verify-data-layer.sh` | OrbStack daemon started; B for file locations | Yes — Lab DB only |

Groups B and C are independent of the FileVault blocker and of each other. Within C, the two ADRs
are sequenced **before** `docker-compose.yml` to satisfy Principle II.

## Complexity Tracking

> No Constitution Check gate FAILS on design grounds. Gate 8's blocked status is an environment
> state remediated by operator action, not a design complexity requiring justification.

Two departures from the P0 plan's *scheduling* are recorded here for traceability. Neither adds a
component, a dependency, or a service.

| Departure | Why needed | Simpler alternative rejected because |
|---|---|---|
| ADR-018 and ADR-019 authored now, not in المرحلة 11 | Principle II requires the ADR to exist *before* the code that acts on the deviation; المرحلة 2 is that code | Following the plan's schedule would write the compose file nine phases before the ADRs justifying its port and engine — the exact "resolved silently in code" outcome the Governance section forbids |
| `docs/runbooks/safety.md` added (not in P0 §6) | Clarification Q3 chose a committed custody record for the recovery key; it needs a committed home that is not the snapshot provenance file | Folding it into `snapshot.md` conflates two different lifetimes — snapshot provenance changes when the snapshot is refreshed, custody changes when the key moves |
