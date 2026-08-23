---
description: "Task list for Handover & P1 Readiness (P0 — المرحلة 10 مختصرة + المرحلة 11)"
---

# Tasks: Handover & P1 Readiness (P0 — المرحلة 10 مختصرة + المرحلة 11)

**Input**: `spec.md`, `plan.md`, `notes.md`, `contracts/source-access-and-stack.md`

**Tests**: `php artisan test` proves the reader and the guardrails; `scripts/verify-*.sh` prove
infrastructure; `php artisan lab:health` is an executable test and this increment's acceptance
instrument. No new framework, no coverage target.

**Format**: `[ID] [P?] [Story]` — `[P]` = parallelisable · US1 P1 readiness · US2 the stack command
and README · US3 the records · `[OPERATOR]` = needs a human.

---

## Shape

```text
Phase 1  US1  the allowlist split   (§3.2)        🎯 the piece P1 waits on
Phase 2  US2  the stack command     (المرحلة 11)   ← independent of Phase 1
Phase 3  US3  records and rehearsal (المرحلة 11+10) ← needs both
Phase 4  Acceptance — P0's §13 record
```

No foundational phase: nothing here is shared infrastructure, and Phases 1 and 2 touch disjoint
files. The re-scope on 2026-08-23 removed the twelve durability tasks and five of the seven memory
tasks; what is left is 19 tasks, and three of them are documents that already exist in draft.

---

## Phase 1: US1 — The allowlist split (§3.2)

**Goal**: reading and copying become different acts, and P1's profiling pack stops being blocked by a
rule written about copying.

**Independent test**: `php artisan test --filter='Allowlist|Forbidden|ReadOnly'` — the six profile
tables read, the fifteen forbidden ones throw by name, no profile table is copyable, and each of the
three write-blocking layers still refuses alone.

- [X] T001 [US1] `apps/lab/config/lab.php` — add `profile_tables` with the six names
  (`course_user`, `course_order`, `orders`, `user_roles`, `roles`, `book_course`) and re-comment
  `source_tables` so it says plainly that it governs **copying**, not reading. Each block states
  which act it governs and cites P0 §3.2 — the comment is what stops the next reader merging them
  (FR-001)
- [X] T002 [US1] `apps/lab/app/Support/SourceReader.php` — `assertReadable()` accepts either list;
  `assertCopyable()` accepts `source_tables` only; `table()` and `count()` go through
  `assertReadable()`. Keep the existing `assertAllowed()` as a deprecated alias **or** update every
  caller — do not leave two names meaning different things. The union must **not** be reachable as a
  copy check: that separation is the entire safety property of the split (FR-002)
- [X] T003 [US1] `apps/lab/tests/Feature/ForbiddenTableRefusalTest.php` — seventeen names → **fifteen**,
  one assertion each, each refused **by name**: `users`, `book_order`, `coupons`, `certificates`,
  `complaints`, `complaint_responses`, `social_providers`, `personal_access_tokens`, `paymob_logs`,
  `zoom_users`, `audits`, `telescope_entries`, `google_oauth_tokens`, `failed_jobs`, `settings`.
  `orders` and `course_order` leave this list because they join it in `profile_tables`, and the test's
  docblock must say so — an unexplained shrinking list is how an allowlist erodes (FR-003, SC-001)
- [X] T004 [US1] `apps/lab/tests/Feature/SourceTableAllowlistTest.php` — add the assertions that make
  the split safe rather than merely wider: each of the six profile tables is **readable**, each is
  **not copyable**, and each of the eleven source tables is both (FR-004, SC-001, SC-002)
- [X] T005 [US1] Re-run `ReadOnlyGuardTest` and `NoPiiInLabSchemaTest` unchanged, and
  `php artisan lab:health`. All three layers must still refuse alone, no Lab column may hold personal
  data, and all ten checks must still pass — check 10 targets `users`, which is still forbidden
  (FR-005, FR-006, SC-003)

**Checkpoint**: P1 can read what it needs to answer §6, and still cannot store what it must not.

---

## Phase 2: US2 — The stack command (المرحلة 11)

**Goal**: one command starts a work session.

**Independent test**: `scripts/lab-stack.sh up` twice, then `ps` — exactly one queue worker and one
service process; then `down`, then `up` with Ollama stopped → non-zero and a clear message.

- [X] T006 [US2] `scripts/lab-stack.sh` — `up | down | status` over the container, the queue worker
  and the service, using `scripts/lib/output.sh` for the line format and the verdict. `#!/bin/bash`,
  bash 3.2 only (see the forbidden-construct list in that file's header). Exit `0` all in the
  requested state · `1` at least one is not · `2` cannot run (FR-007)
- [X] T007 [US2] `scripts/lab-stack.sh` — idempotency: ownership by pid file **plus** a liveness check
  on the recorded pid, so `up` twice leaves one worker and one service, a stale pid file after a
  reboot does not block a start, and an unrelated process is never adopted (FR-008, SC-007)
- [X] T008 [US2] `scripts/lab-stack.sh` — the model runtime is **checked, not started**: Ollama is the
  official macOS app (§8 item K). Absent → say so plainly, exit non-zero, start nothing else that
  depends on it. No login item, no launchd agent, no supervisor (FR-009, SC-008). The final line
  points at `php artisan lab:health`, which is the verdict (FR-010, SC-006)

**Checkpoint**: `scripts/lab-stack.sh up && php artisan lab:health` is the whole morning routine.

---

## Phase 3: US3 — Records and rehearsal (المرحلة 11 + 10)

**Goal**: the documents a second reader needs, each carrying something that was measured or run.

**Independent test**: T017, the clean-folder rehearsal — the criterion itself, not a proxy for it.

### The §6 profiling pack

- [X] T009 [P] [US3] `sql/profiling/01-…sql` through `18-…sql` — the eighteen §6 queries, **verbatim**
  from §6.1 and §6.2 of the program plan, one numbered runnable file each, numbering preserved so a
  §6 reference resolves to a file. Do not "clean up" a query: a rewritten query silently answers a
  different question than the one the program's estimates were built on (FR-019)
- [X] T010 [P] [US3] Give every file a header naming the tables it reads and its allowlist status —
  copy, profile-only, or both. With Phase 1 in place **no query is blocked**; queries 15, 16 and 18
  are marked profile-only, and the pack must state that rather than carrying a stale warning
  (FR-020, SC-005)
- [X] T011 [P] [US3] `sql/profiling/README.md` — how P1 runs the pack, which queries read
  profile-only tables and what that means (read as counts, never stored), and the standing rule that
  none of them was executed in P0 (FR-021)

### The runbooks

- [X] T012 [P] [US3] `docs/runbooks/setup.md` — the pitfalls, each with its measured value: 5432 is
  `postgresql@14` and untouchable so the Lab is on 5433; host `pg_dump`/`psql` is **14.18** and aborts
  against the **17.11** server so all SQL runs in-container; PHP **8.4.2** by absolute path, never
  `brew link`, because 31 local projects depend on the linked 8.2.27; `/bin/bash` is **3.2**; the chat
  model loads before the embedding model (FR-013)
- [X] T013 [P] [US3] `docs/runbooks/memory-check.md` — the manual steps and nothing more: what to run,
  what each number means, what to do about it. It records the 2026-08-23 measurement (stack
  **5,132 MiB**, ~90% of it the two models; `mysqld` 18.6 MiB, container 394.7 MiB host RSS) and the
  two traps from notes N4 — `ps` RSS undercounts idle processes, and `docker stats` must never be
  summed with the OrbStack VM's host RSS. It states plainly that there is **no gate and no acceptance
  criterion**, and it MUST NOT contain a threshold, a script, or a schedule (FR-014, SC-011).
  **Also retire the surviving gate**: `scripts/verify-model-runtime.sh --with-memory` currently
  **fails** on `SYSTEM_CEILING_MIB=13312` (line 41). It must report the number and warn, never block —
  a blocking gate there contradicts constitution v2.1.0. Delivered in 002; corrected here
- [X] T014 [P] [US3] `docs/runbooks/snapshot.md` — resolve `refresh_policy`, or mark it **explicitly
  owed before P1** (§8 item E). Silence is the one outcome that is not allowed (FR-015)

### The README and the records

- [X] T015 [US3] `README.md` — a clean folder to a green `lab:health` in copy-pasteable steps, English
  throughout, no step that exists only in another document. First command
  `scripts/lab-stack.sh up`; last command `php artisan lab:health` (FR-011)
- [X] T016 [US3] Assert mechanically that every committed environment template — root, `apps/lab`,
  `apps/ai-service` — lists every key its real file uses, with no values. This increment adds no key,
  so this is a re-verification, not new work (FR-017, SC-013)
- [X] T017 [US3] The clean-folder rehearsal (FR-012, SC-009, SC-010): stop the live stack, clone to a
  scratch folder, follow the README **verbatim** under an isolated container project and volume name
  so the live data volume cannot be touched, reach ten green checks, tear the rehearsal down
  **including its volume**, restart the live stack, and re-run `lab:health` against the original data.
  If a step was needed that the README did not state, add it and **re-run the rehearsal**

**Checkpoint**: someone else could rebuild this, and it has been demonstrated rather than asserted.

---

## Phase 4: Acceptance — P0's §13 record

- [X] T018 `docs/acceptance/p0-acceptance.md` — §13's **eighteen** criteria, one line each, every one
  either met with its evidence (a command, its output, a date) or explicitly not met with the reason
  (FR-018, SC-015). Confirm ADR-018, ADR-019 and ADR-021 still match the running system — they were
  amended in the 2026-08-23 governance pass, so this is a check, not a rewrite (FR-016, SC-012)
- [X] T019 Re-run everything and record it: the six `scripts/verify-*.sh`, `php artisan test`, and
  `php artisan lab:health`. Assert zero rows written to `injazedu` and **no §6 query executed**
  against it. The repository boundary check and the no-PII schema assertion must both still pass
  (SC-004, SC-014)

---

## Dependencies

```text
Phase 1 (US1, allowlist) ─┐
                          ├─► Phase 3 (US3, records) ─► Phase 4
Phase 2 (US2, stack) ─────┘
```

- **Phase 1** and **Phase 2** are independent and touch disjoint files.
- **T001 → T002 → T003 ‖ T004 → T005**: configuration, then the reader, then the tests that prove the
  separation is real rather than nominal.
- **Phase 3** needs both: T010's headers state the allowlist status Phase 1 produces, and T015's first
  command is Phase 2's script.
- **T015 → T017**: the rehearsal follows the README, so the README exists first. Everything else in
  Phase 3 precedes T017, because T017 is what proves it.

### Parallel opportunities

```text
Phase 1 ‖ Phase 2               entirely independent
T003 ‖ T004                     two test files
T009 ‖ T010 ‖ T011              the profiling pack, different files
T012 ‖ T013 ‖ T014              three runbooks, no shared state
```

---

## What is deliberately NOT here

```text
Any backup, dump, schedule, or restore    ← cancelled program-wide (constitution v2.1.0, §14.6)
Any memory script, gate, or criterion     ← cancelled; manual steps only (FR-014)
Running any §6 profiling query            ← P1 المرحلة 1
Any ETL, import, or student_ref           ← P1 — but assertCopyable() is waiting for it
A Laravel migration, table, or column     ← the applications are finished as of 003
A new .env key                            ← the split is config/lab.php, not .env
A login item or supervisor for services   ← the stack is started for a work session (FR-009)
A new ADR                                 ← the split is a revision to ADR-021, where it belongs
```

If a task here starts to settle a question the operator has not settled, it is not a task — it is an
open question for `plan.md` (Principle I).

---

## Summary

| Phase | Story | Tasks | Delivers |
|---|---|---:|---|
| 1 | US1 (P1) 🎯 | 5 | Two allowlists, separately enforced; §6 queries 15, 16, 18 unblocked |
| 2 | US2 (P2) | 3 | One idempotent command that starts a work session |
| 3 | US3 (P3) | 9 | The §6 pack, three runbooks, README proven by rehearsal |
| 4 | — | 2 | §13's eighteen criteria with evidence; source proven untouched |
| | | **19** | |

One task needs a human: **T017**, the clean-folder rehearsal — it stops the live stack for a few
minutes and needs someone to read the README as a stranger would.

**MVP**: Phase 1. Five tasks, and P1 can start the morning after — it is the only work in P0 that the
next project is actually waiting on.
