<!-- SPECKIT START -->
**P0 is complete and closed.** Next up: **P1 — Production Profiling & Question Mirror**, delivered
as **one** Spec Kit feature (`005-p1-profiling-and-question-mirror`) on branch
`p1/profiling-and-mirror-schema`. Not four increments — one spec covers the whole project.

Read before writing code for it:

- Project plan: `docs/plans/project/1/p1-production-profiling-and-question-mirror.md` (v2.0, leaned
  2026-08-25) — ten phases, fourteen mirror tables, the derivation core, the ETL, the console
- Program §16: `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md`
- Production schema: `docs/schema/injazedu-db-schema.md` — **where plan and schema disagree, the
  schema wins**
- Contract still in force: `specs/004-handover-and-p1-readiness/contracts/source-access-and-stack.md`
  — **P1's ETL is the second party**: it calls `assertCopyable()` before every write
- P0 record (implemented, do not rebuild): `specs/001…004/`, `docs/plans/project/0/p0-ai-lab-foundation.md`

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
non-read · `SourceReader`, which refuses by name any table outside **two** lists.

**Two allowlists since 2026-08-23** (P0 §3.2, ADR-021 revised) — reading and storing are different
acts: `source_tables` (11) may be **copied into** the Lab · `profile_tables` (6) —
`course_user`, `course_order`, `orders`, `user_roles`, `roles`, `book_course` — may be **read as
counts and never stored**, which unblocks §6 queries 15, 16 and 18 · the remaining **15** are refused
in both directions, `users` among them, so `lab:health` check 10 is unaffected. Never use the union
as a copy check: `assertReadable()` and `assertCopyable()` are separate on purpose.

Never propose creating a MySQL user, issuing a `GRANT`, adding a password, or moving the database
into Docker. Those were considered and declined.

**The snapshot is fixed at 2026-08-07 and is never refreshed.** It is the source for the entire
local program (2026-08-25; constitution III, core plan §14.1, P1 §2.2). Never propose taking a new
snapshot, a refresh cadence, or any gate that blocks on the copy's age — the date travels with every
number as *context*, never as a threshold. The operator may inspect, query, transform, or modify the
local copy freely; read-only is a property of the **Lab application**, enforced by the three layers.

**Never propose a backup, a dump schedule, or a restore drill.** Cancelled program-wide on
2026-08-23 (constitution v2.1.0, core plan §14.6, P0 المرحلة 9): this is a development machine, the
snapshot is disposable, and the Lab database is 8.4 MB reproducible from `migrate` + `lab:health`.
Reviewer decisions are the one irreproducible artefact, they do not exist until P2, and protecting
them is a go-live concern.

**Never propose a memory gate, ceiling, or acceptance criterion on a memory number.** Also cancelled
2026-08-23. Manual steps live in `docs/runbooks/memory-check.md`.

## Decisions already fixed — do not re-litigate

`apps/lab/.env` is standard Laravel and holds every application key; the root `.env` holds only
`LAB_DB_PASSWORD` for Docker Compose, and the two must agree on that one value · `DB_*` is Lab
Postgres (the default connection), `INJAZEDU_DB_*` is the MySQL source · Ollama runs as the official
macOS app and login item with defaults, and no runtime limits are pinned · the Filament panel runs the ten health checks **on demand**, persisting nothing ·
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

## Measured 2026-08-23 (end of P0)

`lab:health` passes **10/10, exit 0, 7.058 s cold** — the baseline not to break, and the instrument
every P1 phase is checked against · the Lab database is **8,398 kB**, 12 tables · `pg_dump`/`psql` on the
host is 14.18 and aborts against the 17.11 server at connect time, so **all SQL runs in-container** ·
`/bin/bash` 3.2 does support `set -o pipefail` and `${PIPESTATUS[n]}`.

**Memory — no gate, and here is why.** Stack with both models resident: **5,132 MiB**, ~90% of it the
two models. MySQL is 18.6 MiB, the container 394.7 MiB host RSS, Laravel 13.3 MiB, the service
58.8 MiB — every one an order of magnitude below §12.3's estimate. So **performance work belongs in
the pipeline** (batch sizes, filter cascades, how many model calls happen), not in tuning databases
that cost 20 MB. Two measurement traps: `ps` RSS undercounts idle processes on macOS (`mysqld` reads
6.3 MiB at rest), and `docker stats` must never be summed with the OrbStack VM's host RSS — two views
of the same memory, observed moving in opposite directions.

`scripts/lab-stack.sh up | down | status` starts the stack for a work session — never as login
items.
<!-- SPECKIT END -->

## Project governance

Before writing any spec, plan, or code, read `.specify/memory/constitution.md` (v2.2.0). It is
binding and short.

**Principle I is narrow (2026-08-25).** Stop and ask only for: a change to a data boundary or a
security property · anything expensive to reverse once data exists (the mirror schema shape,
`STUDENT_REF_PEPPER`, the embedding contract) · new infrastructure, services, accounts, or
dependencies · a change to the current project's scope. Everything else — ordinary architecture,
patterns, class boundaries, library choices, anything an edit can undo — **decide with judgement and
state it in the plan**.

**Documentation policy.** Code first · tests for important behaviour · documentation only when it
has continuing practical value. Prefer updating an existing document over creating another. Do not
write a new report, runbook, ADR, handover document, acceptance record, or checklist unless there is
a clear practical reason. A runbook needs a real manual procedure that will be performed again; an
ADR needs a decision that is architectural **and** durable **and** expensive to reverse.
Implementation decisions, measurements, and normal commands live in the code, the tests, the config,
`README.md`, or the project plan.

**Gate policy.** Gates protect real engineering properties: no writes to the source, no PII in Lab
storage, ETL correctness, idempotency where re-running is expected, model/eval quality thresholds.
Procedural gates are not gates and are not written — mandatory report authoring, documentation
review, handover sign-off, memory numbers, snapshot age.

**Testing.** Domain logic, transformations, data integrity, security boundaries, important failure
cases, ETL idempotency/resume, AI structured-output contracts. Not trivial framework behaviour,
accessors, or UI wiring. Tests reduce meaningful risk; they do not maximise count.

The rest of the constitution: what the Lab is · the data boundaries above · deterministic core with
AI at the edge · one coherent Arabic-first surface · a measured 16 GB budget.

This is a **single-developer, local-only** project. Prefer
`requirement → implementation → meaningful test` over documentation chains.

Program plan: `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0).
Production schema: `docs/schema/injazedu-db-schema.md`.
Why the process looks like this: `docs/plans/lean-development-process.md`.
