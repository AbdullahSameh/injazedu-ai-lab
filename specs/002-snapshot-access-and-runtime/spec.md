# Feature Specification: Source Access & Lab Runtime (P0 — المراحل 3–5)

**Branch**: `p0/snapshot-access-and-runtime` · **Created**: 2026-08-21 · **Status**: Draft
**Implements**: `docs/plans/project/1/p0-ai-lab-foundation.md` — المرحلة 3 (source access),
المرحلة 4 (Ollama and the models), المرحلة 5 (the Laravel application).
**Predecessor**: `specs/001-lab-foundation-bootstrap` — a machine verified safe to hold the local
copy, a repository whose committed boundary is mechanically enforced, and a Lab database on 5433.

---

## Scope

Three phases, one question: *can the Lab now reach everything it needs — the InjazEdu source data, a
model runtime, and an application that owns its own schema — without any of those reaches doing harm?*

| Phase | Outcome |
|---|---|
| المرحلة 3 | A read-only connection to the local InjazEdu MySQL database, guarded in the application, reading only the eleven allowlisted tables. |
| المرحلة 4 | A local model runtime on loopback with both models present, and its real memory cost measured rather than estimated. |
| المرحلة 5 | An application that owns every Lab migration, runs on PHP 8.4 without disturbing the machine's other projects, holds both connections, runs a real queued job, and exposes an authenticated panel shell. |

**Out of scope** — writing any of this here is a defect:

```text
The FastAPI service and /embed                     ← المرحلة 6
The embedding contract and prefix                  ← المرحلة 6
php artisan lab:health                             ← المرحلة 7
STUDENT_REF_PEPPER and anonymisation               ← المرحلة 8
Backup script and restore drill                    ← المرحلة 9
Full-stack memory measurement and go/no-go         ← المرحلة 10
README and sql/profiling (18 queries)              ← المرحلة 11
Any Filament resource or functional screen         ← P1
Any ETL, import, or student_ref                    ← P1
Any real AI call, prompt, or stored vector         ← P2
Any connection to injazedu.co                      ← forbidden program-wide
```

Where a later phase depends on something created here — a log channel with no entries, a panel page
with no data, a model nothing calls — this increment creates the **container only**, never its contents.

---

## The Architecture This Increment Establishes

```text
Native MySQL 9.1 on 127.0.0.1:3306        Docker PostgreSQL 17 + pgvector
database: injazedu                        127.0.0.1:5433 · injazedu_lab
user: root / no password                                ▲
              │                                         │
              │  READ / COPY ONLY                       │  READ / WRITE
              └──────────► apps/lab (Laravel 13) ───────┘
```

MySQL does not enforce the read-only direction — the connection uses `root`, which has full
privilege. That trade-off is deliberate and accepted (`docs/ADR/ADR-021.md`). Read-only is enforced
in the application, in three layers, each independently testable.

---

## User Scenarios

### US1 — Read the InjazEdu source without being able to harm it (P1)

The operator needs the Lab to read the question bank, the course tree, and the answer history —
29,142 questions, 1,136,204 results, 13,776,378 answer rows. The same database holds 57,482 user
records, ~70,000 orders, and ~24,408 API tokens. Lab code must not be able to reach those by
accident, and must not be able to modify anything at all.

**Independent test**: connect, count rows in an allowlisted table, then confirm from the application
that a write throws and that a table outside the allowlist throws — with no model runtime present.

**Acceptance**:

1. Reading an allowlisted table returns the expected count (29,142 questions).
2. An `INSERT`, `UPDATE`, or `DELETE` attempted through the application's source connection throws
   before it reaches the database, and no row changes.
3. Asking the source reader for a table outside the allowlist throws, naming the table.
4. Removing the query listener leaves the connection's empty write-host list still refusing; putting
   it back and removing the write-host guard leaves the listener still throwing. Each blocks alone.
5. No Lab migration creates a column that could hold personal data.

### US2 — An application that owns the Lab's schema and does real work (P2)

The operator needs the Lab's application to exist: owning every Lab migration, connecting to the Lab
database it inherited, holding the source connection read-only, actually running a background job
rather than reporting a reachable queue, offering a login-protected panel that later projects build
into, and writing to its own log channel with the fields every future AI call must carry — all on
PHP 8.4 without changing the runtime that 31 other local projects depend on.

**Independent test**: migrate against the Lab database, dispatch a job and watch a worker complete
it, log in to the panel, confirm the log channel received an entry.

**Acceptance**:

1. Migrations complete against the Lab database and its own tables exist.
2. The application executes under PHP 8.4 **and** the machine's linked PHP is still 8.2.
3. A dispatched job is executed by a worker, with an effect that persists after the worker exits.
4. The panel requires login; after login it loads.
5. The health page renders a **stated placeholder** naming المرحلة 7 as the owner of its content —
   no fabricated "green" status anywhere in the panel.
6. An event logged to the Lab channel lands in a dated file separate from the framework's default
   log, retained fourteen days, carrying the fields reserved for later AI calls.
7. The repository boundary check from the previous increment still passes once the dependency trees
   exist, and the two environment files agree on the Lab database password.

### US3 — A model runtime that stays inside its budget and its machine (P3)

Both models present, loadable together, reachable only from this machine, and honest about what they
cost — so المرحلة 10's decision rests on a measurement rather than the plan's estimate.

**Independent test**: list the models, probe liveness, attempt a non-loopback connection, measure
memory with both resident. No other phase need exist.

**Acceptance**:

1. Both models are present under exactly the standardised tags.
2. The runtime responds on loopback and refuses a connection from any other address on the machine.
3. With both models loaded, memory use is measured and recorded against the §12.3 budget line. A
   figure that breaks the 13 GB idle ceiling is reported as a go/no-go trigger, not absorbed.
4. A named tag that cannot be retrieved stops the increment and is recorded — never substituted,
   because model identity is part of a contract that later invalidates stored vectors.

---

## Edge Cases Worth Naming

- **The queue "works" without a worker.** A reachable queue connection is not evidence. The job must
  be observed to have executed, with a row that survives the worker exiting.
- **A panel page that fakes health.** The page has no data source until المرحلة 7. A hard-coded green
  status would make the eventual real check indistinguishable from a placeholder.
- **Dependency resolution under the wrong runtime.** Composer resolves against the running
  interpreter. Resolving under the linked 8.2 can lock a tree 8.4 must then run, or fail outright.
  The runtime used for installation must be the runtime used for execution.
- **The framework's write-block may not refuse.** An empty write-host list might fall back to the
  read host rather than throwing. It must be proven, not assumed — which is why the query listener
  exists as a second, independent layer.
- **The framework brings its own ignore rules** and thousands of dependency files. The repository
  boundary must be re-verified afterwards, not assumed to still hold.
- **Two environment files can drift.** Docker Compose reads the root `.env`; Laravel reads
  `apps/lab/.env`. They share one value — the Lab database password — and a mismatch surfaces as a
  confusing connection error. Assert they match.
- **The runtime may bind wider than loopback.** Inspect the listening socket directly, never infer it
  from a configuration value.
- **Context length set globally.** Context length is a per-call parameter because the cost is cache
  memory. Setting it here would silently change every later call's memory profile.

---

## Requirements

### Source access (المرحلة 3)

- **FR-001**: The application MUST define a connection to the local `injazedu` MySQL database using
  `root` with an empty password, configured with **no write target**.
- **FR-002**: A query listener on that connection MUST throw on any statement that is not a read
  (`SELECT` / `SHOW` / `DESCRIBE` / `EXPLAIN`). The two layers MUST be verified to block independently.
- **FR-003**: A source reader MUST refuse any table outside the eleven allowlisted tables
  (`categories`, `courses`, `chapters`, `lectures`, `quizzes`, `sections`, `questions`, `options`,
  `quiz_files`, `results`, `question_result`), naming the table it refused.
- **FR-004**: No statement that modifies the `injazedu` database may be executed at any point in this
  increment.
- **FR-005**: The source credentials MUST live only in the untracked `apps/lab/.env`; the committed
  template MUST carry the same keys with the password empty.
- **FR-006**: The source data's capture date (2026-08-07) MUST remain readable by the application, so
  every later report can stamp the data it describes.
- **FR-007**: An executable script under `scripts/` MUST verify the connection without the
  application layer: all eleven allowlisted tables readable, and `questions` = 29,142.

### Model runtime (المرحلة 4)

- **FR-008**: A local model runtime MUST be installed with Ollama's official macOS installer and run
  as the app's registered login item so it resumes after login following a machine restart.
- **FR-009**: The runtime MUST listen on loopback only and MUST refuse connections from any other address.
- **FR-010**: Both models MUST be present under exactly the standardised tags — `gemma4:e2b-it-qat`
  and `embeddinggemma:300m-qat-q4_0`. A tag that cannot be retrieved MUST stop the increment.
- **FR-011**: Memory with both models resident MUST be measured and recorded against the §12.3 line.
  An overrun MUST be reported as a go/no-go trigger. Concurrency and residency limits are pinned
  **only if** the measurement shows the budget is at risk — measure first.
- **FR-012**: The increment MUST NOT set a global context length; that is a per-call parameter owned
  by المرحلة 6.
- **FR-013**: An executable script MUST report liveness, both model tags, the effective loopback
  binding, and — with a flag — the resident memory figure.
- **FR-014**: Apart from the empty preload requests required to prove that both models can be
  resident together, this increment MUST NOT issue a generation or embedding call. The preload
  MUST produce neither generated text nor embedding vectors, MUST NOT define a prompt, and MUST
  NOT establish the embedding contract.

### Application shell (المرحلة 5)

- **FR-015**: The application MUST run on PHP 8.4 invoked explicitly and MUST NOT change the
  machine's linked PHP. Dependency resolution MUST use the same binary the application runs under.
- **FR-016**: The application MUST own every Lab migration and MUST migrate successfully against the
  Lab database inherited from the previous increment. Postgres is the default connection.
- **FR-017**: The queue MUST use the database driver. A test job MUST be dispatched and **actually
  executed by a worker**, with an observable effect that persists after the worker exits.
- **FR-018**: An authenticated panel MUST be installed with a working login and exactly one page — a
  **stated placeholder** naming المرحلة 7. No fabricated status, no resources, no functional screens,
  and no locale lock-in that would stop P1 bringing Arabic + RTL with it.
- **FR-019**: A dedicated Lab log channel MUST exist — daily files, fourteen-day retention, separate
  from the framework default — carrying the fields every later AI call must record (model name,
  prompt version, latency). Created empty.
- **FR-020**: `apps/lab/.env` MUST hold the application's configuration and MUST NOT be committed.
  The root `.env` MUST hold only what Docker Compose reads. The one value they share — the Lab
  database password — MUST be asserted equal.
- **FR-021**: The repository boundary check from the previous increment MUST be re-run after the
  application and its dependencies exist, and MUST still pass.
- **FR-022**: The application MUST contain no business logic, no data transformation, no import, and
  no functional review screen.
- **FR-023**: Application-level guarantees MUST be proven by the framework's own test runner;
  infrastructure MUST be proven by shell scripts. All operator-facing output stays English.
- **FR-024**: No Lab migration may create a column capable of holding personal data, and a test MUST
  assert this.

---

## Success Criteria

- **SC-001**: All eleven allowlisted tables are readable and `questions` returns 29,142.
- **SC-002**: A write attempted through the source connection throws — verified for `INSERT`,
  `UPDATE`, and `DELETE`, with zero rows changed.
- **SC-003**: Each write-blocking layer blocks alone: with the listener removed the connection still
  refuses, and with the write-host guard removed the listener still throws.
- **SC-004**: A table outside the allowlist throws, naming the table.
- **SC-005**: Zero rows are written to `injazedu` during this increment, and the Lab schema holds no
  column capable of holding personal data.
- **SC-006**: Both models are present and load together, with memory measured against §12.3 in one run.
- **SC-007**: The runtime refuses every connection attempt from a non-loopback address.
- **SC-008**: The application migrates against the Lab database on first run, and the machine's
  linked PHP is unchanged afterwards — verified before and after.
- **SC-009**: A dispatched test job is observed to have been executed by a worker, its effect still
  present after the worker stops.
- **SC-010**: The panel requires authentication, loads after login, and its single page states that
  its content arrives in المرحلة 7 — zero fabricated status indicators.
- **SC-011**: The repository boundary check passes unchanged after the dependency trees exist, and
  the two environment files agree on the shared password.

---

## Assumptions

Measured on this machine 2026-08-21:

- MySQL **9.1.0** on `127.0.0.1:3306`, database `injazedu`, `root` with an empty password, bound to
  loopback. All eleven allowlisted tables exist as base tables.
- The bank is **29,142** questions — the measured size, replacing the plan's ~25,000 estimate.
- PHP **8.2.27** is linked and 31 local projects depend on it. **8.4.2** is available at
  `/opt/homebrew/opt/php@8.4/bin/php`. Laravel 13.26.1 requires `php ^8.3`. Never `brew link`.
- PHP 8.4 already has `pdo_pgsql`, `pdo_mysql`, and `mysqlnd` — no extension install is needed.
- Ollama **0.32.15** was installed on 2026-08-22 with the official macOS installer. The app lives at
  `/Applications/Ollama.app`, exposes its CLI through `/usr/local/bin/ollama`, and registers as a
  login item.
- The Lab database container from the previous increment is running and healthy on 5433.
- `/bin/bash` is 3.2 — no bash 4+ syntax in any script.

Verified 2026-08-21: PHP 8.4 authenticates as `root` with an empty password over TCP against MySQL
9.1's `caching_sha2_password` without TLS, and reads `questions` = 29142 (notes.md N1). No open
assumption remains.

## Dependencies

- `001-lab-foundation-bootstrap` remains accepted and its three verifications passing.
- MySQL and the container engine running on loopback; network access for package and model downloads.
- No connection to `injazedu.co` or any remote environment, in this or any other increment.

## Handoff to المرحلة 6

```text
A source connection that reads eleven tables and throws on everything else,
  with each of its three guards proven to block independently
A model runtime on loopback with both models present
A measured memory figure for both models resident, against the §12.3 line
An application owning every Lab migration, with a working queue, an
  authenticated panel shell, and a log channel whose fields the first AI
  call must fill
```

Still open by design: no service exists, so nothing calls a model and no embedding contract is
fixed. No vector has been stored — which is why fixing that contract is المرحلة 6's first act.
