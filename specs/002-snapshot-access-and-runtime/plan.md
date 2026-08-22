# Implementation Plan: Source Access & Lab Runtime (P0 — المراحل 3–5)

**Branch**: `p0/snapshot-access-and-runtime` · **Date**: 2026-08-21 · **Spec**: [spec.md](./spec.md)

## Summary

Deliver المراحل 3–5: a guarded read-only connection to the local InjazEdu MySQL database, a local
model runtime holding both program models inside a measured memory budget, and a Laravel application
that owns every Lab migration, runs a real queued job, and holds both database connections.

Two things shape the approach:

1. **MySQL enforces nothing.** The connection uses `root` with no password, which is the approved
   architecture (`docs/ADR/ADR-021.md`). Read-only is an *application* property here, built in three
   independent layers so that removing any one still leaves the others blocking. That is what SC-002
   through SC-004 test.
2. **Proof is split by tool.** Infrastructure — is MySQL reachable, is Ollama loopback-only, is the
   repo boundary intact — is proven by shell scripts. Application behaviour — the guard throws, the
   allowlist throws, no PII column exists — is proven by `php artisan test`. Neither reaches into the
   other's territory.

## Technical Context

**Language/Version**: PHP **8.4.2** at `/opt/homebrew/opt/php@8.4/bin/php`, never linked · bash
**3.2.57** for scripts (no bash 4+ syntax)
**Primary dependencies**: `laravel/framework` **v13.26.1** (requires `php ^8.3`) ·
`filament/filament` **v5.7.6** · Composer 2.8.4 · official Ollama macOS app **0.32.15** · MySQL client 9.1.0
**Storage**: PostgreSQL 17 + pgvector on `127.0.0.1:5433` (inherited) — default connection, read/write ·
MySQL 9.1.0 on `127.0.0.1:3306`, database `injazedu` — source connection, read-only by application
**Testing**: three shell scripts for infrastructure; Pest/PHPUnit for the three guardrail tests
**Target platform**: macOS (Darwin 25.5.0), Apple M1 Pro, 16 GB, 149 GB free
**Constraints**: idle stack ≤ **13 GB**, with the chat model measured at 4,135 MB against a ~3 GB
plan line · loopback-only for the model runtime and both databases · zero rows written to `injazedu` ·
the machine's linked PHP must remain 8.2.27

## Checks Before Building

- [x] **Nothing decided that needed approval** — the four decisions this increment rests on (env
      layout, the read-only guard shape, the allowlist's new home, how Ollama runs) were all put to
      the operator and approved. No open question is being resolved unilaterally.
- [x] **Read-only toward InjazEdu MySQL** — the whole point of المرحلة 3. Three layers, each tested.
- [x] **No PII into the Lab** — nothing is stored at all in this increment; FR-024 asserts the Lab
      schema holds no column that could carry personal data.
- [x] **Laravel owns migrations** — the only migration created is Laravel's. No metric is computed
      and no AI output is parsed.
- [x] **Tests are the targeted kind** — three shell scripts and three guardrail tests. No coverage
      target, no e2e suite.
- [x] **Fits the budget** — no LLM call and no batch exists. The applicable clause is the memory
      ceiling, and FR-011 makes measuring it an acceptance step.

## Two Forks Worth Planning For

| Condition | Decision |
|---|---|
| PHP 8.4 cannot run Laravel 13 + Filament 5 | Fall back to Laravel 12 + Filament 5 on PHP 8.2, per P0 §11. Measured as unlikely: 13.26.1 requires `php ^8.3` and 8.4.2 satisfies it. |
| Idle stack with both models resident exceeds **13 GB** | Apply the plan's remedies — lower `num_ctx` at call sites, separate embedding batches from chat, shorten residency — and **write the decision down**. Pin the Ollama limits at that point, not before. Do not silently accept the overrun. |

Two smaller ones, handled inline rather than as forks: if PHP cannot authenticate as `root` over TCP
(notes.md N1) the increment stops and asks before working around it; if the empty write-host list
falls back to the read host instead of refusing (N3), the query listener carries the guarantee alone
and the script records which mechanism is in force.

## Project Structure

`✅` created here · `✏️` amended · `📁` placeholder for a later phase

```text
injazedu-ai-lab/
├── apps/
│   ├── lab/                                    ✅ المرحلة 5 — Laravel 13 + Filament 5
│   │   ├── .env                                ✅ untracked; the app's configuration
│   │   ├── .env.example                        ✅ committed; every key, no values
│   │   ├── config/database.php                 ✅ pgsql default + injazedu (read-only)
│   │   ├── config/lab.php                      ✅ source_tables allowlist + snapshot_taken_at
│   │   ├── config/logging.php                  ✅ the `lab` channel, daily, 14 days
│   │   ├── app/Providers/AppServiceProvider.php ✅ the read-only query listener
│   │   ├── app/Support/SourceReader.php        ✅ throws outside the allowlist
│   │   ├── app/Jobs/LabQueueProbe.php          ✅ the job that must actually run
│   │   ├── app/Filament/Pages/LabHealth.php    ✅ stated placeholder — المرحلة 7 fills it
│   │   ├── database/migrations/                ✅ framework defaults + lab_job_probes
│   │   └── tests/Feature/                      ✅ three guardrail tests
│   └── ai-service/                             📁 المرحلة 6
├── infrastructure/postgres/init.sql            (001)
├── sql/profiling/                              📁 المرحلة 11 — 18 queries
├── scripts/
│   ├── preflight-check.sh                      (001)
│   ├── verify-repo-boundary.sh                 ✏️ + 2 assertions
│   ├── verify-data-layer.sh                    (001)
│   ├── verify-injazedu-access.sh               ✅ FR-007
│   ├── verify-model-runtime.sh                 ✅ FR-013
│   └── verify-lab-app.sh                       ✅ FR-017, FR-020, FR-021
├── docs/
│   ├── ADR/{018,019,021}.md                    ✏️ 021 rewritten for this architecture
│   └── runbooks/{safety,snapshot}.md           ✏️ snapshot.md's access line updated
├── .gitignore                                  ✏️ narrow `*.sql` → dumps only
├── .env                                        ✏️ shrinks to what Docker Compose reads
└── .env.example                                ✏️ same
```

**Structure notes.** `sql/grants/` and `infrastructure/launchd/` do **not** exist — the first because
there is no grant to issue, the second because the official Ollama macOS app owns its login item and
runs with defaults until a measurement says otherwise. `README.md` remains المرحلة 11's.

## Implementation Grouping

| Group | Covers | Depends on | Touches InjazEdu MySQL? |
|---|---|---|---|
| **A — Source access** (المرحلة 3) | `.gitignore` narrowing, `verify-injazedu-access.sh` | nothing | Yes — reads eleven tables, writes nothing |
| **B — Model runtime** (المرحلة 4) | Official macOS app, both model pulls, `verify-model-runtime.sh` | nothing | No |
| **C — Application** (المرحلة 5) | Laravel + Filament, both connections, the three guards, `lab` log channel, probe migration + job, panel, `verify-lab-app.sh`, boundary re-verification, the three tests | A (for the connection it configures), 001's Lab database | Yes — reads; every write must throw |

B is independent and can run at any point, including first — the 4.1 GB pull is the long pole. A and
C are the ordered spine: C cannot prove SC-002 through SC-004 before A confirms the connection works.
