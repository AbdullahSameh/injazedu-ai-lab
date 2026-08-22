<!-- SPECKIT START -->
Active feature: **003-service-health-guardrails** — P0 AI Lab Foundation, المراحل 6–8
(stateless FastAPI service + the embedding contract · `lab:health` with ten checks, two of which pass
only by being refused · the remaining guardrails made executable).

Read before writing code for it:

- Spec: `specs/003-service-health-guardrails/spec.md` (27 FRs, 16 SCs)
- Plan: `specs/003-service-health-guardrails/plan.md`
- Notes: `specs/003-service-health-guardrails/notes.md` (6 measured findings, 2026-08-22)
- Data model: `specs/003-service-health-guardrails/data-model.md`
- Contract: `specs/003-service-health-guardrails/contracts/ai-service.md` — Laravel's checks 4–6 are
  written against endpoints Python implements; both sides must agree
- Quickstart: `specs/003-service-health-guardrails/quickstart.md`
- Tasks: not generated yet — run `/speckit-tasks`
- Predecessors (implemented): `specs/002-snapshot-access-and-runtime/` (المراحل 3–5) and
  `specs/001-lab-foundation-bootstrap/` — historical framing, current artefacts

## The data boundary

```text
Native MySQL 9.1 · 127.0.0.1:3306      Docker PostgreSQL 17 + pgvector
database: injazedu                     127.0.0.1:5433 · injazedu_lab
user: root / no password                             ▲
           │                                         │
           │  READ / COPY ONLY                       │  READ / WRITE
           └────────► apps/lab (Laravel 13) ─────────┘
```

**MySQL enforces nothing** — the connection uses `root` with full privilege. That is approved
(`docs/ADR/ADR-021.md`). Read-only is an application property, in three layers that must each block
alone: an empty write-host list on the `injazedu` connection · a query listener that throws on any
non-read · `SourceReader`, which refuses any table outside the eleven in `config/lab.php`.

Never propose creating a MySQL user, issuing a `GRANT`, adding a password, or moving the database
into Docker. Those were considered and declined.

## Decisions already fixed — do not re-litigate

`apps/lab/.env` is standard Laravel and holds every application key; the root `.env` holds only
`LAB_DB_PASSWORD` for Docker Compose, and the two must agree on that one value · `DB_*` is Lab
Postgres (the default connection), `INJAZEDU_DB_*` is the MySQL source · Ollama runs as the official
macOS app and login item with defaults, and limits are pinned **only if** a measurement breaches the
13 GB ceiling · the Filament panel is login + one **stated placeholder** page (المرحلة 7 fills it) ·
ADRs are written only for durable architectural decisions — not for a PHP binary, an `.env`
location, or a Docker setting.

## Environment facts measured 2026-08-21; Ollama updated 2026-08-22

FileVault **On** · `/bin/bash` is 3.2 (no bash 4+ syntax) · Lab Postgres 17 healthy on 5433; 5432 is
`postgresql@14` (untouchable) · host `psql` is 14 vs server 17, so run SQL in-container · **Ollama
0.32.15 official macOS app installed** on 2026-08-22 · PHP 8.2.27 is linked and 31 local projects
depend on it — use
`/opt/homebrew/opt/php@8.4/bin/php` (8.4.2) explicitly for **both** resolve and run; never
`brew link` · Laravel 13.26.1 needs `php ^8.3`; Filament 5.7.6 · PHP 8.4 already has
pdo_pgsql/pdo_mysql/mysqlnd · 149 GB free.

MySQL source — 9.1.0 on `127.0.0.1:3306`, database `injazedu`, **50 tables** (11 on the copy
allowlist). Bank is **29,142** questions. `root@localhost` is password-less by decision (ADR-021).

Verified 2026-08-21: PHP 8.4 authenticates as `root` with an empty password over TCP against MySQL
9.1's `caching_sha2_password` without TLS. If a future change breaks this, **stop and ask** — do not
enable TLS, create a user, or change the auth plugin.

## Fixed by 003, measured 2026-08-22

The embedding contract is `eg300m-qat-q4_0/sim-v1/768/l2norm` — model tag, the mandatory prefix
`task: sentence similarity | query: {text}`, 768 dimensions, L2-normalized. Changing any component
silently invalidates every stored vector (§12.2). The **service** applies the prefix and the
normalization; callers send raw text · the runtime already returns unit-length vectors, so
normalizing is defensive, and a zero-norm result is an **error**, never something to normalize ·
truncation is **silent**: `prompt_eval_count >= context_length` (2048, read from `/api/show`) is the
only signal · pgvector round-trips float32 exactly, so the vector check asserts equality, not a
threshold, using a **generated** vector rather than a model output · Laravel 13 has a native
`$table->vector($col, 768)` · **load the chat model before the embedding model** — the reverse order
evicts the embedding runner on this 16 GB machine.

The service is started **by hand** this increment; the one-command stack starter is المرحلة 11's.
<!-- SPECKIT END -->

## Project governance

Before writing any spec, plan, or code, read `.specify/memory/constitution.md` (v2.0.0). It is
binding and short. Principle I has priority: **do not decide architecture, infrastructure,
security, database, dependency, or workflow questions the repo or the operator has not settled** —
identify the problem, give the options, recommend one, then ask. Ordinary implementation that
follows from an approved architecture needs no such gate.

The rest: what the Lab is · the data boundaries above · deterministic core with AI at the edge ·
targeted testing only · one coherent Arabic-first surface · a measured 16 GB budget.

This is a **single-developer, local-only** project. Prefer
`requirement → implementation → meaningful test` over documentation chains.

Program plan: `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0).
Production schema: `docs/schema/injazedu-db-schema.md`.
Why the process looks like this: `docs/plans/lean-development-process.md`.
