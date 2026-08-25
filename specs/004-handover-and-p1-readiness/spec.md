# Feature Specification: Handover & P1 Readiness (P0 — المرحلة 10 مختصرة + المرحلة 11)

**Branch**: `p0/handover-and-p1-readiness` · **Created**: 2026-08-23 · **Status**: Draft
**Implements**: `docs/plans/project/1/p0-ai-lab-foundation.md` — المرحلة 10 (reduced to manual steps,
2026-08-23), المرحلة 11 (documentation, the one-command start, the §6 profiling pack), plus §3.2 (the
copy/profile allowlist split).
**Predecessor**: `specs/003-service-health-guardrails` — a stateless service on loopback, the fixed
embedding contract, `lab:health` with ten checks (two passing only by refusing), guardrail tests.

> **Revised 2026-08-23.** An earlier draft of this increment covered المراحل 9–11 and centred on a
> nightly backup and a restore drill. The operator cancelled المرحلة 9 outright and reduced المرحلة 10
> to a manual checklist: this is a development machine, the snapshot is disposable, and there is
> nothing in an 8.4 MB Lab database worth protecting nightly. Constitution v2.1.0 and core plan §14.6
> record the removal. What remains is the work that makes P0 finishable and P1 startable.

---

## Scope

One question: *P0's parts all work and prove themselves — what is still missing before this stops
being a foundation under construction and becomes a thing you build P1 on tomorrow morning?*

| Piece | Outcome |
|---|---|
| **The allowlist split** (§3.2) | Reading and storing become different acts. Three of the program's own profiling queries stop being blocked by a rule written about copying. |
| المرحلة 11 | One command starts the stack; a README takes a clean folder to a green `lab:health`; the runbooks carry measured values; the §6 pack is written and every one of its eighteen queries is runnable in P1. |
| المرحلة 10 (reduced) | One short runbook of manual steps for when the machine feels slow. No script, no gate, no acceptance criterion on a memory number. |

**Out of scope** — writing any of this here is a defect:

```text
Any backup, dump schedule, or restore drill      ← cancelled program-wide (constitution v2.1.0)
Any memory script, gate, or measured criterion   ← cancelled (المرحلة 10, 2026-08-23)
Running any §6 profiling query                   ← P1 المرحلة 1
Any ETL, import, mirror table, or student_ref    ← P1
Any Filament resource beyond the existing panel  ← P1
Any prompt, prompt version, or stored vector     ← P2
Any similarity, duplicate, or scoring logic      ← P2
Any vector index (HNSW)                          ← earned, not assumed
Any connection to injazedu.co                    ← forbidden program-wide
```

---

## What This Increment Inherits

| Needed here | State on arrival |
|---|---|
| `lab:health` — ten checks, exit ≠ 0 on any deviation | Delivered (003). Measured **10/10, exit 0, 7.058 s cold** on 2026-08-23 — this increment's acceptance instrument |
| Three write-blocking layers, each blocking alone | Delivered (002) — **unchanged** by the allowlist split |
| `SourceReader` + `config('lab.source_tables')` (11 tables) | Delivered (002) — **extended** here with a second list |
| `ForbiddenTableRefusalTest` (17 names) | Delivered (003) — **corrected** here to 15, plus a new read-not-copy assertion |
| A stateless service started by hand on 8001 | Delivered (003) — gets a supervised start here |
| `docs/runbooks/{snapshot,safety}.md`, `ADR-018/019/021` | Delivered (001–002) — re-checked and amended on 2026-08-23 |
| Six `scripts/verify-*.sh` | Delivered (001–003) — joined, not replaced |

---

## The Change That Unblocks P1

```text
BEFORE                                  AFTER (§3.2, 2026-08-23)

source_tables (11)                      source_tables  (11)   may be COPIED into the Lab
  ├─ governs reading                    profile_tables ( 6)   may be READ as counts, never copied
  └─ governs copying                      course_user · course_order · orders
                                          user_roles  · roles        · book_course
forbidden (17)                          forbidden     (15)   refused by name, both directions
                                                              users among them
```

§6 queries **15, 16 and 18** read `course_user`, `course_order`, `orders`, `user_roles`, `roles` and
`book_course`. They answer two questions the program cannot proceed without — which table actually
records enrolment (`course_user` vs `course_order`), and whether `course_user` holds students or
trainers — and they were blocked by a rule written about **copying**, not reading. Reading a count is
not storing a row.

The guarantee that carries the weight is untouched: **no PII column exists in the Lab database**,
proven by `NoPiiInLabSchemaTest` against the schema itself, which is indifferent to how wide the read
list is. `lab:health` check 10 targets `users`, still refused in both directions.

---

## User Scenarios

### US1 — P1 can start on day one (Priority: P1)

The next project begins by **running** the §6 profiling pack, not by writing it and not by
discovering that three of its queries are refused. All eighteen queries exist as numbered files, and
every one of them can actually execute against the source.

**Why this priority**: it is the whole point of finishing P0. §6 is "the first practical task in the
program, performed before anything is built" — every capacity estimate in §13 and every scope
decision in §6.3 waits on its output.

**Independent test**: run the guardrail suite — the six profile tables are readable, the fifteen
forbidden ones are refused by name, and no profile table is copyable. Then open `sql/profiling/` and
confirm eighteen numbered files with no blocked-query warnings left in them.

**Acceptance Scenarios**:

1. **Given** the six profile tables, **When** each is requested through the source reader, **Then**
   each returns rather than throwing.
2. **Given** the fifteen forbidden tables, **When** each is requested by name, **Then** each throws,
   naming that table.
3. **Given** a profile table, **When** it is checked against the copy allowlist, **Then** it is
   **not** copyable — reading and storing are separately enforced.
4. **Given** the three write-blocking layers, **When** each is disabled in turn, **Then** the
   remaining layer still refuses the write — the split changed guard 3 only.
5. **Given** `sql/profiling/`, **When** it is inspected, **Then** it holds the eighteen §6 queries as
   numbered runnable files, each naming the tables it reads and its allowlist status.
6. **Given** the whole increment, **When** the source is inspected, **Then** **no** profiling query
   has been executed and zero rows have been written to `injazedu`.
7. **Given** `lab:health`, **When** it runs after the split, **Then** all ten checks still pass —
   check 10 still refused `users`.

---

### US2 — One command up, and a README that actually works (Priority: P2)

The operator starts a work session with one command instead of five, and a second reader — or the
operator in two months — can take a clean folder, follow the README, and reach a green `lab:health`.

**Why this priority**: it is P0's definition of done (§1.3) and the last of §13's criteria that has
no evidence yet. It also removes a daily friction: today the container, the worker and the service
are each started by hand.

**Independent test**: from a scratch clone, follow the README top to bottom without consulting any
other file, and end at ten passing health checks.

**Acceptance Scenarios**:

1. **Given** a stopped machine, **When** the single start command runs, **Then** the container, the
   queue worker and the service are up, and it reports what it started and what it could not.
2. **Given** the start command is run twice, **When** the second run finishes, **Then** nothing is
   duplicated — no second worker, no second service process.
3. **Given** the model runtime is not running, **When** the start command runs, **Then** it says so
   plainly and exits non-zero, having started nothing it does not own.
4. **Given** the README alone, **When** its steps are followed on a clean folder, **Then** the run
   ends at ten passing health checks with no undocumented step.
5. **Given** the rehearsal is finished, **When** it is torn down and the live stack restarted,
   **Then** `lab:health` passes ten checks against the original data.

---

### US3 — The records a second reader needs (Priority: P3)

Four short documents that each carry a real measured value or a real executed procedure, and one
acceptance record that says plainly which of §13's criteria are met and which are not.

**Why this priority**: it depends on the other two being finished, and it is the part most easily
diluted into prose nobody executes. Its criterion is behavioural: every runbook line is a command
that was run or a number that was measured.

**Independent test**: read each runbook and find no placeholder; read the acceptance record and find
evidence or an explicit reason on every line.

**Acceptance Scenarios**:

1. **Given** the setup runbook, **When** it is read, **Then** every pitfall carries the measured
   value behind it — the port conflict, the client/server version gap, the PHP binary, the shell
   version, the model load order.
2. **Given** the memory runbook, **When** the operator feels the machine is slow, **Then** it gives
   three commands, what each number means, and what to do — and states plainly that there is no gate.
3. **Given** the snapshot runbook, **When** its refresh policy is read, **Then** it is either
   resolved or explicitly marked as owed before P1 — never silent.
4. **Given** the three ADRs, **When** each is compared to the running system, **Then** every stated
   fact still holds.
5. **Given** the acceptance record, **When** any criterion is read, **Then** it carries either its
   evidence or the reason it is not met.

---

### Edge Cases Worth Naming

- **A widened read list that quietly widens storage.** The split is only safe while the two lists are
  separately enforced. A single call that checks the union before an insert would undo it. The copy
  check is its own method, and the schema assertion is the backstop.
- **The forbidden list silently shrinking further.** Moving two names out of a seventeen-name test is
  exactly the kind of change that could keep going. The fifteen remaining are enumerated explicitly,
  one assertion each, so the next widening has to be deliberate.
- **A start command that hides a failure.** Backgrounding four processes and returning 0 regardless
  teaches the operator nothing. It reports per-component status, and `lab:health` is the verdict.
- **A start command that stacks duplicates.** Run twice, it must not leave two workers competing for
  the same jobs table — and a stale pid file after a reboot must neither block a start nor let it
  adopt an unrelated process.
- **A rehearsal that eats the live volume.** A scratch clone using the same container project name
  reuses — and `down -v` destroys — the live data volume. The rehearsal is isolated by name, and the
  live volume's survival is asserted afterwards.
- **A README fixed but never re-run.** Correcting a missing step and not re-running the rehearsal
  produces exactly the artefact the criterion exists to prevent.
- **A memory runbook that grows a threshold.** The moment it says "if it exceeds X, fail", the gate is
  back. It describes symptoms and remedies, never a pass mark.
- **Profiling queries written from memory.** The eighteen must match §6.1 and §6.2 exactly; a
  "cleaned up" query silently answers a different question than the one the program planned around.

---

## Requirements

### The allowlist split (§3.2)

- **FR-001**: `config/lab.php` MUST carry **two** lists: `source_tables` — the existing eleven,
  unchanged, governing what may be **copied into** the Lab — and `profile_tables` — six additional
  tables readable for aggregate profiling and never copied: `course_user`, `course_order`, `orders`,
  `user_roles`, `roles`, `book_course`. Each list MUST carry a comment stating which act it governs.
- **FR-002**: `SourceReader` MUST permit reading any table in **either** list and refuse, by name,
  anything outside both. It MUST expose a **separate** copy check, so a future ETL asks a different
  question than a reader does. The union MUST NOT be usable as a copy check.
- **FR-003**: The forbidden-table test MUST assert **fifteen** names, one assertion each: `users`,
  `book_order`, `coupons`, `certificates`, `complaints`, `complaint_responses`, `social_providers`,
  `personal_access_tokens`, `paymob_logs`, `zoom_users`, `audits`, `telescope_entries`,
  `google_oauth_tokens`, `failed_jobs`, `settings`.
- **FR-004**: A test MUST assert that each of the six profile tables is **readable but not copyable**
  — the property that makes the split safe rather than merely wider.
- **FR-005**: The three write-blocking layers MUST still block independently after the change, and
  `lab:health` MUST still pass all ten checks, check 10 included.
- **FR-006**: No Lab migration or column may be added by this change, and the no-PII schema assertion
  MUST still pass unchanged.

### The one-command stack (المرحلة 11)

- **FR-007**: One command MUST start the whole stack — database container, queue worker, service —
  reporting per-component status and exiting non-zero if any component fails to come up. A matching
  stop path and a status path MUST exist.
- **FR-008**: The start command MUST be **idempotent**: running it twice MUST leave exactly one queue
  worker and one service process. A stale pid file MUST neither block a start nor cause an unrelated
  process to be adopted.
- **FR-009**: The command MUST NOT start the model runtime — Ollama is the official macOS app (§8
  item K). It MUST **check** it and report plainly if it is absent. It MUST NOT install a login item,
  background agent, or process supervisor; the stack is started for a work session.
- **FR-010**: The command MUST NOT be the verdict: its final line MUST point at `php artisan
  lab:health`, which is.

### Documentation and records (المرحلة 11)

- **FR-011**: `README.md` MUST take a reader from a clean folder to a green `lab:health` in
  copy-pasteable steps, with no step that exists only in another document. Operator-facing text stays
  English (Constitution VI).
- **FR-012**: The README MUST be verified by **execution on a clean folder**: the live stack stopped,
  a scratch clone set up by following the README verbatim under an **isolated container project and
  volume name**, ten passing checks, teardown **including the rehearsal volume**, then the live stack
  restarted and re-verified. A step the reader needed that the README did not state MUST be added and
  the rehearsal re-run.
- **FR-013**: `docs/runbooks/setup.md` MUST carry the pitfalls measured on this machine, each with
  its value: 5432 is `postgresql@14` and untouchable so the Lab is on 5433; the host `pg_dump`/`psql`
  is **14.18** against a **17.11** server so all SQL runs in-container; PHP **8.4.2** is invoked by
  absolute path and never `brew link`ed because 31 local projects depend on the linked 8.2.27;
  `/bin/bash` is **3.2**; the chat model loads before the embedding model.
- **FR-014**: `docs/runbooks/memory-check.md` MUST give the manual steps only — what to run, what
  each number means, what to do about it. It MUST state that there is **no gate and no acceptance
  criterion** on a memory number, and MUST record the 2026-08-23 measurement showing the stack at
  ~5.1 GB with ~90% of it the two models, so a future reader can see where tuning would and would not
  help. It MUST NOT introduce a threshold, a script, or a schedule. Any **surviving** memory gate in a
  delivered script MUST be retired to a warning — `scripts/verify-model-runtime.sh --with-memory`
  currently blocks on a 13,312 MiB ceiling, which contradicts constitution v2.1.0.
- **FR-015**: `docs/runbooks/snapshot.md`'s `refresh_policy` MUST be either resolved or explicitly
  marked as owed before P1. Silence is the one outcome that is not allowed.
- **FR-016**: `ADR-018`, `ADR-019` and `ADR-021` MUST each match the running system. No new ADR may be
  written for an ordinary implementation choice.
- **FR-017**: Every committed environment template — root, `apps/lab`, `apps/ai-service` — MUST list
  every key its real file uses with **no values**, asserted mechanically. This increment adds no key.
- **FR-018**: The eighteen acceptance criteria in §13 MUST each be recorded as met with its evidence,
  or explicitly as not met with the reason.

### The §6 profiling pack (المرحلة 11)

- **FR-019**: `sql/profiling/` MUST hold the **eighteen** §6 queries as individually numbered runnable
  `.sql` files, matching §6.1 and §6.2 in content and numbering.
- **FR-020**: Each file MUST carry a header naming the tables it reads and its allowlist status —
  copy, profile-only, or both. With the split in place **no query is blocked**, and the pack MUST say
  so rather than carrying a stale warning.
- **FR-021**: No profiling query may be executed against the snapshot in this increment. Zero rows are
  written to `injazedu`.

---

## Key Entities

- **Copy allowlist**: the eleven tables that may be written into the Lab. Unchanged since 002.
- **Profile allowlist**: six tables readable as aggregates and never stored. New here; the thing that
  unblocks P1.
- **Profiling query file**: one numbered `.sql` file carrying a §6 query, the tables it reads, and its
  allowlist status. Written in P0, executed in P1.
- **Acceptance record**: §13's eighteen criteria with evidence per line — what makes "P0 is done" a
  checkable claim rather than an announcement.

---

## Success Criteria

- **SC-001**: Each of the six profile tables is readable through the source reader; each of the
  fifteen forbidden tables is refused by name, one assertion each.
- **SC-002**: No profile table is copyable — read and copy are separately enforced.
- **SC-003**: Each of the three write-blocking layers still refuses alone, and `lab:health` passes all
  ten checks after the split.
- **SC-004**: Zero rows are written to `injazedu`, and no §6 query is executed in this increment.
- **SC-005**: `sql/profiling/` contains eighteen numbered files matching §6, each naming its tables
  and allowlist status, with no query marked blocked.
- **SC-006**: One command brings the stack up; `lab:health` then reports ten passes with exit 0.
- **SC-007**: Running the start command twice leaves exactly one queue worker and one service process.
- **SC-008**: With the model runtime stopped, the start command reports it and exits non-zero.
- **SC-009**: Following the README from a clean folder — live stack stopped, rehearsal isolated by
  container project and volume name — reaches ten passing health checks with no step taken from
  outside the README.
- **SC-010**: The live Lab data volume still holds its original data after the rehearsal, proven by a
  post-rehearsal `lab:health` and the unchanged vector probe value.
- **SC-011**: All three runbooks exist and contain no placeholder; each carries a measured value or an
  executed procedure, and `memory-check.md` contains no threshold.
- **SC-012**: Every fact stated in ADR-018, ADR-019 and ADR-021 matches the running system.
- **SC-013**: Every committed environment template lists every key of its real file with no values.
- **SC-014**: The repository boundary verification and the no-PII schema assertion both still pass.
- **SC-015**: Every one of §13's eighteen criteria is recorded as met with evidence or as not met with
  a reason.

---

## Assumptions

Measured on this machine, 2026-08-23 unless noted:

- `lab:health` passes **10/10, exit 0, 7.058 s** from cold — the baseline this increment must not
  break, and the instrument it is accepted by.
- The Lab database is **8,398 kB**, 12 tables. There is nothing in it that `php artisan migrate` and
  `lab:health` cannot recreate — which is why المرحلة 9 was cancelled.
- The stack costs **5,132 MiB** with both models resident; ~90% is the two models. `mysqld` is
  **18.6 MiB** (buffer pool 128 MiB), the container **394.7 MiB** host RSS, Laravel **13.3 MiB**, the
  service **58.8 MiB** — every one an order of magnitude under §12.3. Performance work therefore
  belongs in the pipeline, not in database tuning.
- Host `pg_dump`/`psql` is **14.18** and aborts against the **17.11** server at connect time; all SQL
  runs in-container. This survives in `setup.md` as a pitfall even though المرحلة 9 is gone.
- `/bin/bash` is **3.2.57** — no bash 4+ syntax, in scripts or in anything the README asks a reader to
  run.
- PHP **8.2.27** is linked and 31 local projects depend on it; **8.4.2** at
  `/opt/homebrew/opt/php@8.4/bin/php` runs this application. Never `brew link`.
- Ollama **0.32.15** runs as the official macOS app with defaults; the chat model must load before the
  embedding model (002 N5, 003 N5).
- macOS **26.5.2**, Apple M1 Pro, 16 GB, FileVault On, 134 GiB free.
- The six profile tables exist in the snapshot and are readable; §6 nos. 15, 16 and 18 are written
  against them.

---

## Dependencies

- `003-service-health-guardrails` accepted, with `lab:health` passing all ten checks.
- The database container, the model runtime, MySQL and the service reachable on loopback.
- No connection to `injazedu.co` or any remote environment, in this or any other increment.

---

## Handoff to P1

```text
Two allowlists: 11 copyable, 6 profile-only, 15 refused by name
The §6 pack written, numbered, unexecuted — and all eighteen runnable
One command that starts the stack, and a README proven from a clean folder
Runbooks carrying measured values, and three ADRs matching reality
The §13 acceptance record: eighteen lines, each met with evidence or not met with a reason
```

P1 begins by running the pack and replacing every estimate in §13 of the program plan with a measured
number — which is what §6 was written for.

Still owed before P1, by design: the snapshot refresh policy (§8 item E).
