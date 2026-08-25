# Implementation Plan: Service, Health Matrix & Guardrails (P0 — المراحل 6–8)

**Branch**: `p0/service-health-guardrails` · **Date**: 2026-08-22 · **Spec**: [spec.md](./spec.md)
**Phase 0 findings**: [notes.md](./notes.md) — six measurements taken before any code

## Summary

Deliver المراحل 6–8: a stateless FastAPI service on loopback that is the Lab's only door to the model
runtime and that fixes the embedding contract before a single vector exists; a `php artisan lab:health`
command running ten checks — two of which pass only by being refused — with a non-zero exit on any
deviation; and the remaining guardrails made executable rather than remembered.

Three things shape the approach:

1. **The two inverted checks are the point.** Checks 9 and 10 attempt a write to the source and a read
   of a forbidden table, and pass only when both are refused. Everything else in this increment is
   plumbing that could be rebuilt in a day; those two are the difference between "read-only" being a
   sentence in `ADR-021` and being a property that fails loudly when it stops holding.
2. **The embedding contract is the durable artefact.** §12.2 is explicit that changing the model tag,
   prefix, dimension, or normalization silently invalidates every stored vector. P2 stores ~58,300 of
   them. The contract is fixed here, returned on every call, and — per N1 — made structurally true by
   normalizing in our own code rather than trusting the runtime to keep doing it.
3. **Proof stays split by tool.** Infrastructure is proven by shell scripts, application behaviour by
   `php artisan test`, and the service by its own suite. The health command sits above all three and
   proves they are *connected* — it does not replace any of them (FR-020).

## Technical Context

**Language/Version**: Python **3.13.7** via `uv` 0.10.12 for the service · PHP **8.4.2** at
`/opt/homebrew/opt/php@8.4/bin/php`, never linked · bash **3.2.57** for scripts (no bash 4+ syntax)
**Primary dependencies**: `fastapi`, `uvicorn`, `httpx`, `pydantic`, `asyncpg` (new) ·
`laravel/framework` **v13.26.1** · `filament/filament` **v5.7.6** · `livewire/livewire` **v4.4.1** ·
`guzzlehttp/guzzle` **8.0.2** (already present — N6)
**Storage**: PostgreSQL 17 + pgvector 0.8.6 on `127.0.0.1:5433` — read/write for Laravel, **read-only**
for the service · MySQL 9.1.0 on `127.0.0.1:3306` — read-only by application, and the service has no
credential for it at all
**Model runtime**: Ollama 0.32.15 on `127.0.0.1:11434` · `gemma4:e2b-it-qat` (3,393 MiB resident) ·
`embeddinggemma:300m-qat-q4_0` (276.3 MiB resident, 2048-token window, 768 dimensions)
**Testing**: PHPUnit 12.5 via `php artisan test` for application guardrails · the service's own suite
for the contract · shell scripts for infrastructure
**Target platform**: macOS (Darwin 25.5.0), Apple M1 Pro, 16 GB
**Constraints**: idle stack ≤ **13 GB** · loopback-only for the service, the runtime, and both
databases · zero rows written to `injazedu` · the machine's linked PHP stays 8.2.27 · the service
persists nothing · no prompt, no stored vector, no index

## Checks Before Building

- [x] **Nothing decided that needed approval** — the three decisions this increment rests on (how the
      service is started, whether the service normalizes, what the panel page does) were put to the
      operator on 2026-08-22 and answered. `checklists/requirements.md` records all three. The Phase 0
      measurements in `notes.md` resolved four further unknowns by measuring rather than choosing.
- [x] **Read-only toward InjazEdu MySQL** — check 9 *attempts* a write for the sole purpose of being
      refused, through the guarded connection. Zero rows change. Nothing else in this increment goes
      near the source except check 8's count and check 10's refusal.
- [x] **No PII into the Lab** — the only new table holds a fixed id and a 768-float vector. FR-025
      re-asserts the existing schema check over it. `STUDENT_REF_PEPPER` is created and consumed by
      nothing (FR-023).
- [x] **Laravel owns migrations** — the vector probe table is a Laravel migration using the framework's
      native `vector()` column (N4). The service owns no migration and writes nothing. No metric is
      computed; no AI output is parsed, because there is no AI output — the chat probe asserts a
      response, never its content.
- [x] **Tests are the targeted kind** — guardrail assertions, a health check that is an executable test
      with a non-zero exit, and a contract assertion on the returned vector's norm. No coverage target,
      no e2e suite, no mocking framework.
- [x] **Fits the ~11–13 GB budget** — no batch and no LLM ration applies yet. Both models resident is
      3,669 MiB measured in `002`; this increment adds a Python process (~0.3 GB). The full-stack
      measurement and the go/no-go call remain المرحلة 10's.

## Two Forks Worth Planning For

| Condition | Decision |
|---|---|
| The on-demand panel run exceeds the web request's time budget | Phase 5 measured a cold authenticated browser action at **12.87 s end to end**; check 5 reported 6.378 s and check 6 reported 1.645 s (N7). The action completed normally in the current local web runtime, and the shared service runtime probe completed within its separate 10-second upstream timeout, so the fork is not triggered. If a future environment times out, keep the CLI as the authority and have the page run the seven non-model checks, stating plainly that checks 5 and 6 are CLI-only. Do **not** fabricate their status, and do **not** raise a limit globally to hide it. |
| The service cannot reach Postgres read-only without its own role | The service uses the same Lab credentials and its read-only status is a property of its code and its review (P0 المرحلة 6 acceptance: *"يُتحقَّق بمراجعة الكود"*). Creating a Postgres role is new security infrastructure — Principle I says **stop and ask**, not decide. SC-010's row-count assertion is the compensating control. |

## Project Structure

`✅` created here · `✏️` amended · `📁` placeholder for a later phase

```text
injazedu-ai-lab/
├── apps/
│   ├── ai-service/                              ✅ المرحلة 6 — FastAPI, stateless
│   │   ├── pyproject.toml / uv.lock             ✅ fastapi, uvicorn, httpx, pydantic, asyncpg
│   │   ├── .env                                 ✅ untracked — Lab DB + runtime URL, NO MySQL keys
│   │   ├── .env.example                         ✅ committed; every key, no values
│   │   ├── app/main.py                          ✅ the five endpoints
│   │   ├── app/config.py                        ✅ settings; EMBEDDING_CONFIG_VERSION
│   │   ├── app/embedding.py                     ✅ prefix, normalization, truncation (N1, N2)
│   │   ├── app/health.py                        ✅ db / runtime / aggregate, each independent
│   │   ├── app/logging.py                       ✅ one structured JSON line per request
│   │   └── tests/                               ✅ contract: 768 dims, norm 1, prefix applied
│   └── lab/
│       ├── app/Console/Commands/LabHealth.php   ✅ المرحلة 7 — the ten checks, exit ≠ 0
│       ├── app/Support/Health/                  ✅ one class per check + a result value object
│       ├── app/Filament/Pages/LabHealth.php     ✏️ placeholder → on-demand run (FR-019)
│       ├── database/migrations/…vector_probes   ✅ $table->vector('embedding', 768)  (N4)
│       ├── config/lab.php                       ✏️ + embedding contract, + service URL
│       ├── .env / .env.example                  ✏️ + STUDENT_REF_PEPPER, EMBEDDING_CONFIG_VERSION,
│       │                                            AI_SERVICE_URL
│       └── tests/Feature/                       ✏️ ForbiddenTableRefusalTest (17 names) + re-runs
├── scripts/
│   ├── verify-ai-service.sh                     ✅ loopback binding, four endpoints, no MySQL keys
│   └── verify-repo-boundary.sh                  ✏️ + the pepper must not be committable
├── .env.example                                 (unchanged — Docker Compose only)
└── docs/                                        📁 runbooks stay المرحلة 11's
```

**Structure notes.** No `scripts/start-lab.sh`, no launchd plist, no process supervisor — the service
is started by hand this increment and the one-command starter is المرحلة 11's, beside the README that
documents it (operator decision, 2026-08-22). No `docs/ADR/` addition: nothing here is architectural,
durable, and expensive to reverse in the way the constitution reserves an ADR for. The embedding
contract comes closest, but it is already fixed by §12.2 of the program plan and recorded in
`contracts/ai-service.md` where the code that must honour it can find it.

## Design Artefacts

Written because each changes what gets built; nothing here restates the spec.

| Artefact | Why it earns its place |
|---|---|
| [notes.md](./notes.md) | Six measurements. N2 alone (truncation is silent; `prompt_eval_count` is the only signal) changes FR-007 from an intention into a buildable rule. |
| [data-model.md](./data-model.md) | Three shapes two components must agree on: the health result, the probe row, the contract string. The CLI and the panel render the same result object. |
| [contracts/ai-service.md](./contracts/ai-service.md) | Laravel's checks 4–6 are written against endpoints Python implements. A second party depends on this, which is exactly the constitution's test for a contract. |
| [quickstart.md](./quickstart.md) | The runnable path for this increment. README is المرحلة 11's. |

## Implementation Grouping

| Group | Covers | Depends on | Touches InjazEdu MySQL? |
|---|---|---|---|
| **A — The service** (المرحلة 6) | `apps/ai-service` scaffold, the five endpoints, the embedding contract, structured logging, its own tests, `verify-ai-service.sh` | The Lab database and the runtime (both inherited) | **No** — and it holds no credential for it |
| **B — Guardrails** (المرحلة 8) | The seventeen-name refusal test, the three env keys, boundary re-verification, the no-PII re-assertion | Nothing new | Only to be refused |
| **C — The health matrix** (المرحلة 7) | The vector probe migration, ten check classes, the command, the panel page | **A** (checks 4–6), **B** (checks 9–10), the probe migration | Checks 8, 9, 10 |

**Order**: A and B are independent and can run in either order — B is the smaller and touches only
files that already exist. C is last by necessity: it is the thing that proves A and B, and half its
checks have no target until A exists.

Within A, the embedding endpoint comes before the health endpoints: `/health/ollama` reports on both
models, and the embedding path is where the model contract is settled. Within C, the two inverted
checks (9, 10) are written first, because a matrix that reports nine greens and forgets why it exists
is the failure mode this whole increment guards against.

## Open Questions

None. The three the spec raised were answered by the operator on 2026-08-22 and are recorded in
`checklists/requirements.md`; the four technical unknowns were resolved by measurement in `notes.md`.

Two things are **deliberately deferred**, not open: whether the service needs its own read-only
Postgres role (Principle I — it would be new security infrastructure, and the fork table above says
ask rather than decide), and the one-command stack starter (المرحلة 11).
