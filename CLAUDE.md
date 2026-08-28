<!-- SPECKIT START -->
**P0 and P1 are complete and closed.** Current: **P2 — Arabic Normalization & Duplicate
Intelligence**, delivered as **one** Spec Kit feature (`006-p2-duplicate-intelligence`) on branch
`p2/duplicate-intelligence`. Eleven phases, one spec.

Read before writing code for it:

- **Spec Kit artefacts (current, read first)**: `specs/006-p2-duplicate-intelligence/` —
  `spec.md` (138 FRs, 5 clarifications), `plan.md` (eleven groups, **no open questions**),
  `notes.md` (nine Phase 0 findings — **N1 and N2 corrected the project plan's numbers**),
  `data-model.md` (the eight tables), `contracts/verdict.md`
- Project plan: `docs/plans/project/2/p2-duplicate-intelligence.md` (v1.0, 2026-08-27) — the cascade,
  the six decisions, the eleven phases
- Program §17: `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md`
- Production schema: `docs/schema/injazedu-db-schema.md` — **where plan and schema disagree, the
  schema wins**
- P1 record (implemented, do not rebuild): `specs/005-p1-profiling-and-question-mirror/`,
  `docs/plans/project/1/p1-production-profiling-and-question-mirror.md`
- P0 record (implemented, do not rebuild): `specs/001…004/`, `docs/plans/project/0/p0-ai-lab-foundation.md`

## P2's measured numbers — every estimate that starts from 28,747 is roughly twice too large

Measured 2026-08-27/28 against the loaded mirror, fixed 2026-08-07 snapshot:

```text
28,747 active questions (29,142 with soft-deleted)  ->  11,416 distinct RAW texts
                                                        11,094 distinct NORMALIZED stems
                                                        12,969 distinct stem + options
4,689 duplicate groups · 22,020 questions · 17,331 redundant rows (60.3%)
   4,558 with no image member ·  928 conflicting (20.4%)
     131 with an image member ·   96 conflicting (73.3%)  <- 3.6x, the media boundary rule
largest duplicate group: 538 members (median 3, p99 15) — a true finding, never a chaining artefact
```

**The embedding budget is ~24,063 calls, not 2 x 11,416** — each embedding deduplicates by its own
hash (stem by text hash, full by stem+options hash), and those grains differ. 58% saved against
57,494. See `notes.md` N1; the project plan's 11,416 figure is the distinct *raw text* count and is
the key for neither embedding.

**The conflict backlog is 928 groups, ~31-77 trainer hours** — not the plan's ~1,125 / 37-94, whose
group tallies did not sum. See `notes.md` N2.

`answer_key_state` values are **`single_correct` · `broken_no_key` · `multi_key`** — the project
plan's `single_key` does not exist. `sections.description` is empty in all 3,316 rows, so the passage
track is inert and its table is asserted empty rather than covered.

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

**Two allowlists** (P0 §3.2, ADR-021 revised 2026-08-23 and 2026-08-26) — reading and storing are
different acts: `source_tables` (**10**) may be **copied into** the Lab · `profile_tables` (**7**) —
`course_user`, `course_order`, `orders`, `user_roles`, `roles`, `book_course`, `question_result` —
may be **read as aggregates and never stored** · the remaining **15** are refused in both directions,
`users` among them, so `lab:health` check 10 is unaffected. Never use the union as a copy check:
`assertReadable()` and `assertCopyable()` are separate on purpose.

**`question_result` is never mirrored** (ADR-022, 2026-08-26). Its 13.8M answer events are unbounded
behavioural data that nothing annotates individually, so they are aggregated by pushdown into
`source_item_stats` and `source_option_stats` (307,382 rows). `source_results` (1.1M attempts) *is*
mirrored. The rule: **mirror what gets enriched and is bounded; aggregate what is only ever counted
and is unbounded.** Never propose re-adding `source_answers`.

Never propose creating a MySQL user, issuing a `GRANT`, adding a password, or moving the database
into Docker. Those were considered and declined.

**The snapshot is fixed at 2026-08-07 and is never refreshed.** It is the source for the entire
local program (2026-08-25; constitution III, core plan §14.1, P1 §2.2). Never propose taking a new
snapshot, a refresh cadence, or any gate that blocks on the copy's age — the date travels with every
number as *context*, never as a threshold. The operator may inspect, query, transform, or modify the
local copy freely; read-only is a property of the **Lab application**, enforced by the three layers.

**Never propose a backup, a dump schedule, or a restore drill.** Cancelled program-wide on
2026-08-23 (constitution v2.1.0, core plan §14.6, P0 المرحلة 9): this is a development machine, the
snapshot is disposable, and the Lab database's **schema** is 8.4 MB reproducible from `migrate` +
`lab:health`. Reviewer decisions are the one irreproducible artefact, they do not exist until P2,
and protecting them is a go-live concern.

**"Reproducible" is not permission to reset `injazedu_lab` (amended 2026-08-27).** It describes the
*schema* being cheap to recreate from `migrate`, not the manually-imported mirror data sitting in
it — re-importing costs real wall-clock time and is not something to trigger casually. Never run
`migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, or `DROP DATABASE`/`DROP SCHEMA`
against `injazedu_lab` — always ask the user first, even when a task seems to call for "a clean
database." `apps/lab/tests/` runs against a separate, disposable `injazedu_lab_test` database
(`.env.testing`); `composer test:mirror` is the only suite allowed to read the real mirror, and it
is read-only/transaction-rolled-back by design. A destructive command against any database other
than `injazedu_lab_test` is refused outright by `AppServiceProvider::guardDestructiveCommands()` /
`guardDestructiveStatements()` (`config('lab.safe_destructive_databases')`) — but that guard is a
backstop, not a substitute for asking.

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

`lab:health` passes **10/10, exit 0** — the baseline not to break, and the instrument every P1 phase
is checked against · the Lab database was 8,398 kB at end of P0 and is **~673 MB** with the mirror
and statistics loaded · `pg_dump`/`psql` on the
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

Before writing any spec, plan, or code, read `.specify/memory/constitution.md` (v2.5.0). It is
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
