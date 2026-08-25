# Implementation Plan: Handover & P1 Readiness (P0 — المرحلة 10 مختصرة + المرحلة 11)

**Branch**: `p0/handover-and-p1-readiness` · **Date**: 2026-08-23 · **Spec**: [spec.md](./spec.md)
**Phase 0 findings**: [notes.md](./notes.md) — five measurements
**Contract**: [contracts/source-access-and-stack.md](./contracts/source-access-and-stack.md)

## Summary

Close P0 and start P1. Three pieces: split the source allowlist so reading and copying are different
acts — which unblocks three of the program's own profiling queries; give the stack one start command
and a README proven by running it on a clean folder; and write the records a second reader needs,
including §13's acceptance list with evidence per line.

> **This increment was re-scoped on 2026-08-23.** It previously covered المراحل 9–11 and was built
> around a nightly backup and a restore drill. The operator cancelled المرحلة 9 entirely and reduced
> المرحلة 10 to a manual checklist. Constitution v2.1.0, core plan §14.6 and §12.3, and P0 §§3.2, 7,
> 10–13 all carry the change. What is left is smaller, and all of it is work that either finishes P0
> or starts P1.

Three things shape the approach:

1. **The allowlist split is the only change with downstream consequences.** Everything else in this
   increment is scripts and documents; this one alters a guarantee that ADR-021 rests on. It is safe
   only because read and copy become **separately enforced questions** — a single union check used
   for both would quietly undo it. The backstop is unchanged and is the one that matters: no Lab
   column can hold personal data, asserted against the schema.
2. **Documentation is executable or it does not count.** The README's criterion is a clean-folder
   rehearsal ending in ten green checks, the runbooks each carry a measured value, and the acceptance
   record lists evidence per line. No document here is prose for its own sake.
3. **The memory work is a runbook, not a gate.** The measurement that killed the gate is kept in
   `notes.md` N4, because a decision without its evidence is an opinion — and because it carries one
   conclusion into P2: ~90% of the stack is the two models, so performance work belongs in the
   pipeline, not in tuning databases that cost 20 MB.

## Technical Context

**Language/Version**: PHP **8.4.2** at `/opt/homebrew/opt/php@8.4/bin/php`, never linked (config, the
reader, three tests) · bash **3.2.57** for `lab-stack.sh` — `set -o pipefail` and `PIPESTATUS` both
verified present, no bash 4+ syntax · SQL for the §6 pack, written not run
**Primary dependencies**: none new. Laravel 13.26.1, Filament 5.7.6, the existing
`scripts/lib/output.sh`
**Storage**: PostgreSQL 17 + pgvector 0.8.6 on `127.0.0.1:5433` — **no migration, no new table, no
new column** · MySQL 9.1.0 on `127.0.0.1:3306`, read-only by application, now with two allowlists
**Testing**: `php artisan test` for the reader and the guardrails; `scripts/verify-*.sh` for
infrastructure; `php artisan lab:health` as the acceptance instrument — measured **10/10, exit 0,
7.058 s cold** (N1)
**Target platform**: macOS **26.5.2** (Darwin 25.5.0), Apple M1 Pro, 16 GB
**Constraints**: zero rows written to `injazedu` · no §6 query executed · no login item, agent, or
supervisor · no memory gate and no acceptance criterion on a memory number (constitution v2.1.0) ·
the machine's linked PHP stays 8.2.27 · `postgresql@14` on 5432 untouched

## Checks Before Building

- [x] **Nothing decided that needed approval** — four decisions were put to the operator on
      2026-08-23 and answered: backup removed from the program entirely, memory reduced to manual
      steps with no gate, the allowlist split into read and copy, and the document edits reaching the
      constitution and core plan. All four are recorded in the documents themselves, not only here.
- [x] **Read-only toward InjazEdu MySQL** — the split widens **reading** by six aggregate tables and
      changes **copying** not at all. Guards 1 and 2 are untouched; guard 3 gains a second list and a
      separate copy check. Zero rows written, and no §6 query executed (FR-021).
- [x] **No PII into the Lab** — no migration, no column, no ETL. `NoPiiInLabSchemaTest` is the
      backstop and must still pass unchanged (FR-006). The six new readable tables are read as counts
      and stored nowhere.
- [x] **Laravel owns migrations** — none are added. No metric computed, no AI output produced; the
      only model calls are the ones `lab:health` already makes.
- [x] **Tests are the targeted kind** — three guardrail assertions extended, one shell script added,
      the existing health command as an executable test. No new framework, no coverage target.
- [x] **Fits the budget** — nothing this increment adds is resident. The budget is no longer a gate
      (constitution v2.1.0); `docs/runbooks/memory-check.md` is the manual replacement.

## One Fork Worth Planning For

| Condition | Decision |
|---|---|
| The README rehearsal exposes a missing step | Add it to the README and **re-run the rehearsal** (FR-012). A README corrected after a failed rehearsal but never re-run is exactly the artefact this criterion exists to prevent. It is cheap: a green run takes 7 s and the database is 8 MB. |

## Project Structure

`✅` created here · `✏️` amended · `📁` untouched

```text
injazedu-ai-lab/
├── apps/lab/
│   ├── config/lab.php                      ✏️ + profile_tables (6), source_tables commented
│   │                                          as the COPY list
│   ├── app/Support/SourceReader.php        ✏️ assertReadable + assertCopyable, separately
│   └── tests/Feature/
│       ├── ForbiddenTableRefusalTest.php   ✏️ 17 names → 15
│       └── SourceTableAllowlistTest.php    ✏️ + profile tables readable, NOT copyable
├── scripts/
│   ├── lab-stack.sh                        ✅ up | down | status, idempotent
│   └── lib/output.sh                       📁 reused unchanged
├── sql/profiling/
│   ├── 01-…sql … 18-…sql                   ✅ the §6 pack, written, NOT executed
│   └── README.md                           ✅ how P1 runs it; all eighteen runnable
├── docs/
│   ├── runbooks/setup.md                   ✅ the measured pitfalls
│   ├── runbooks/memory-check.md            ✅ manual steps, no threshold, no script
│   ├── runbooks/snapshot.md                ✏️ refresh_policy resolved or explicitly owed
│   ├── runbooks/safety.md                  📁 unchanged
│   ├── ADR/ADR-018 · -019 · -021.md        ✅ already re-checked and amended 2026-08-23
│   └── acceptance/p0-acceptance.md         ✅ §13's eighteen criteria, evidence per line
├── README.md                               ✅ clean folder → green lab:health
└── .env.example (×3)                       📁 unchanged — this increment adds no key
```

**Structure notes.** No migration, no table, no service change, no new dependency. `docs/acceptance/`
is a new directory holding one file. The three ADRs were re-checked as part of the 2026-08-23
governance pass and are marked `✅` rather than `✏️` because that work is done — what remains is to
confirm they still match at acceptance (FR-016).

**No new ADR.** A script's CLI shape, a runbook, and a config key are none of them architectural,
durable, and expensive to reverse. The allowlist split comes closest, and it is recorded where it
belongs: as a revision to **ADR-021**, whose guard 3 it changes, plus P0 §3.2 and core plan §14.2.

**No new environment key.** The split is configuration in `config/lab.php`, not `.env`. The root
`.env` still holds only `LAB_DB_PASSWORD`; every application key still lives in `apps/lab/.env`.

## Design Artefacts

| Artefact | Why it earns its place | |
|---|---|---|
| [notes.md](./notes.md) | Five measurements. N2 (which §6 queries are blocked and by which rule) is what the allowlist split is built from; N4 is the evidence behind removing the memory gate, kept so the decision is auditable. | ✅ |
| [contracts/source-access-and-stack.md](./contracts/source-access-and-stack.md) | **P1's ETL is the second party.** It will call `assertCopyable` before every write, and the read/copy separation is a property it must be able to rely on. The stack command's idempotency contract is the other half. | ✅ |
| ~~data-model.md~~ | Skipped: no table, column, or migration is added. | ❌ |
| ~~quickstart.md~~ | Skipped: `README.md` **is** the quickstart, it is a deliverable (FR-011), and it is verified by execution (FR-012). A second copy would drift. | ❌ |

## Implementation Grouping

| Group | Covers | Depends on | Touches InjazEdu MySQL? |
|---|---|---|---|
| **A — The allowlist split** (§3.2) | `config/lab.php`, `SourceReader`, the two guardrail tests | Nothing | Reads six new tables in tests; writes nothing |
| **B — The stack command** (المرحلة 11) | `scripts/lab-stack.sh` | Nothing | No |
| **C — Records and rehearsal** (المرحلة 11 + 10) | The §6 pack, three runbooks, `README.md`, the acceptance record, the clean-folder rehearsal | **A** and **B** | Only as `lab:health` already does |

**Order**: A first — it is the piece with a downstream consequence, and P1 waits on it. B is
independent and can run beside it. C last: the §6 pack's headers state the allowlist status A
produces, and the README's first command is B.

Within A: configuration, then the reader, then the tests — the tests are what prove the separation is
real rather than nominal. Within C: everything before the rehearsal, because the rehearsal is what
proves it.

## Decisions Taken Under Principle I

All four are the operator's, taken 2026-08-23, and recorded in the documents they change.

| Decision | Where it landed |
|---|---|
| **Backup and restore removed from the program entirely** — the machine is a development environment, the snapshot is disposable, and the Lab database (8.4 MB) is reproducible from `migrate` + `lab:health`. Reviewer decisions are the one irreproducible artefact and do not exist until P2; protecting them is a go-live concern. | Constitution III (v2.1.0) · core plan §14.6 · P0 §§4.1, 7 (المرحلة 9 cancelled), 8 item I, 9, 10, 11, 12, 13 |
| **No memory gate and no acceptance criterion on a memory number** — manual steps only. The measured stack is 5,132 MiB with ~90% of it the two models; the old whole-machine ceiling moved with browser tabs and no P0 remedy could have fixed a breach of it. | Constitution VII (v2.1.0) · core plan §12.3, §15 · P0 §§7 (المرحلة 10), 11, 13 · `memory-check.md` (FR-014) |
| **The allowlist splits into copy and profile** — reading a count is not storing a row. Unblocks §6 queries 15, 16 and 18, which resolve how enrolment is recorded. Forbidden set 17 → 15; `users` stays refused, so `lab:health` check 10 is unaffected. | Constitution III (v2.1.0) · core plan §14.2, §6 · P0 §3.2 · **ADR-021 revised** · FR-001…FR-006 |
| **Document edits reach the constitution and the core program plan** — one coherent pass, so no document is left contradicting another. | Constitution → v2.1.0; core plan §§6, 12.3, 14.2, 14.6, 15; P0 plan throughout; ADR-018, -019, -021 |

## Open Questions

None. One item remains open **by design** and is owed before P1, not before this increment: the
snapshot refresh policy (§8 item E) — recorded in `docs/runbooks/snapshot.md` rather than decided
here (FR-015).
