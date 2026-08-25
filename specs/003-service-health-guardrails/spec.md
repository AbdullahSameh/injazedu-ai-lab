# Feature Specification: Service, Health Matrix & Guardrails (P0 — المراحل 6–8)

**Branch**: `p0/service-health-guardrails` · **Created**: 2026-08-22 · **Status**: Draft
**Implements**: `docs/plans/project/1/p0-ai-lab-foundation.md` — المرحلة 6 (the FastAPI service),
المرحلة 7 (the ten-check health matrix), المرحلة 8 (the guardrails).
**Predecessor**: `specs/002-snapshot-access-and-runtime` — a guarded read-only source connection, a
model runtime on loopback with both models measured, and a Laravel application that owns the Lab
schema, runs a real queued job, and exposes an authenticated panel with a stated placeholder page.

---

## Scope

Three phases, one question: *the pieces exist — can the Lab now prove, in one command, that they are
all connected and that the boundaries it claims are real?*

| Phase | Outcome |
|---|---|
| المرحلة 6 | A stateless service on loopback that is the Lab's only path to the model runtime, that fixes the embedding contract **before** any vector is stored, and that logs every call structurally. |
| المرحلة 7 | One command that exercises ten connections — eight that must succeed and **two that must fail** — and exits non-zero on any deviation. |
| المرحلة 8 | The remaining boundaries made executable rather than remembered: every forbidden source table refused **by name**, and the environment keys later phases depend on, present and uncommitted. |

This is the increment where P0 stops being a collection of working parts and becomes a system that
can prove its own claims. Until المرحلة 7 exists, "Production is read-only" is a sentence in a
document; after it, it is a check that fails loudly when it stops being true.

**Out of scope** — writing any of this here is a defect:

```text
Backup script, scheduling, restore drill              ← المرحلة 9
Full-stack memory measurement and the go/no-go call   ← المرحلة 10
README, runbooks, sql/profiling (18 queries)          ← المرحلة 11
Any Filament resource or functional review screen     ← P1
Any ETL, import, or student_ref derivation            ← P1
Any prompt, prompt version, or stored vector          ← P2
Any duplicate/similarity logic or scoring             ← P2
Any vector index (HNSW) — exact scan is the default   ← earned, not assumed
Any connection to injazedu.co                         ← forbidden program-wide
```

Where a later phase depends on something created here — a pepper nothing consumes, a contract string
no stored row carries yet, a log field no AI call has filled — this increment creates the **container
only**, never its contents.

---

## What This Increment Inherits

`002` already delivered three of المرحلة 8's five listed items. Restating them as new work would be
duplication; this increment **re-proves** them and adds only what is genuinely missing:

| المرحلة 8 item | State on arrival |
|---|---|
| Three write-blocking layers, each blocking alone | **Delivered** (`ReadOnlyGuardTest`, notes N3) — re-run, not rebuilt |
| A write through the source connection fails | **Delivered** — re-run |
| Every forbidden table refused by name | **Partial** — the reader refuses by allowlist; the explicit seventeen-name assertion is **new here** |
| `SNAPSHOT_TAKEN_AT` in the environment | **Delivered** (`config/lab.php`) — surfaced in health output here |
| `EMBEDDING_CONFIG_VERSION` in the environment | **New here** (المرحلة 6 fixes the contract) |
| `STUDENT_REF_PEPPER` in the environment | **New here** |

---

## The Architecture This Increment Completes

```text
                        php artisan lab:health  ── 10 checks, exit ≠ 0 on any deviation
                                   │
Native MySQL 9.1 ◄──── READ ONLY ──┤
127.0.0.1:3306                     │
database: injazedu        apps/lab (Laravel 13 + Filament 5)
                                   │            │
                                   │ HTTP/JSON  │ READ / WRITE
                                   ▼            ▼
                   apps/ai-service (FastAPI)   PostgreSQL 17 + pgvector
                   127.0.0.1:8001 · stateless  127.0.0.1:5433 · injazedu_lab
                        │            │                    ▲
                        │            └──── READ ONLY ─────┘
                        ▼
                   Ollama 127.0.0.1:11434
                   gemma4:e2b-it-qat · embeddinggemma:300m-qat-q4_0
```

The service is **stateless** (ADR-013): Laravel owns every Lab migration, and the service reads the
Lab database only to answer "is it reachable". It holds no source-database credentials at all — every
read of InjazEdu goes through Laravel's guarded connection or does not happen.

---

## User Scenarios

### US1 — Prove the whole system in one command (Priority: P1)

The operator needs a single command that answers "is the Lab working, and are its boundaries intact?"
without reading eight terminal windows. Eight checks must pass. **Two must fail** — a write attempted
against the source, and a request for a forbidden table — because a guarantee that is never exercised
is a guarantee that quietly rots.

**Why this priority**: it is P0's definition of done. Every acceptance criterion in §13 of the plan
either is this command or is verified by it. It also makes المرحلة 9's restore drill and المرحلة 10's
memory measurement checkable rather than anecdotal.

**Independent test**: run the command with everything up (all ten pass, exit 0), then stop one
service and run it again (that check fails, names what it could not reach, exit ≠ 0).

**Acceptance Scenarios**:

1. **Given** the stack is running, **When** the health command runs, **Then** ten checks report and
   the command exits 0.
2. **Given** the Lab database container is stopped, **When** the command runs, **Then** the checks
   that need it fail naming it, the rest still report, and the command exits non-zero.
3. **Given** the model runtime is stopped, **When** the command runs, **Then** the chat and embedding
   checks fail naming the runtime and the model tag, and the command exits non-zero.
4. **Given** everything is running, **When** the command runs, **Then** the write-attempt check
   passes **because the write was refused**, and the forbidden-table check passes **because the read
   was refused, naming the table**.
5. **Given** the source check runs, **When** it reports the question count, **Then** it also reports
   the capture date of the data it counted.
6. **Given** any check fails, **When** the operator reads the output, **Then** the failing line names
   the target it could not reach and the reason — never a bare "failed".

---

### US2 — A model runtime the Lab reaches through one door (Priority: P2)

Everything the Lab will ever ask a model goes through one stateless service. That service fixes the
embedding contract now — model tag, prefix, dimension, normalization — because §12.2 is explicit that
changing any part of it **silently invalidates every stored vector**. Fixing it before the first
vector exists costs an afternoon; discovering it after 58,300 vectors exist costs P2.

**Why this priority**: the contract is the durable artefact. The endpoints are scaffolding; the
contract is a decision that P2, P4, P5, and P9 all inherit and cannot cheaply revisit.

**Independent test**: start the service alone, call each health endpoint, call the embedding endpoint
with a known string, and confirm the response carries a vector, its dimension, and the contract
version — with Laravel absent.

**Acceptance Scenarios**:

1. **Given** the service is running, **When** liveness is requested, **Then** it responds with its
   version, having touched neither the database nor the runtime.
2. **Given** the service is running, **When** the aggregate health endpoint is requested, **Then**
   every section reports individually and a failure in one does not mask the others.
3. **Given** a text input, **When** the embedding endpoint is called, **Then** the mandatory
   similarity prefix is applied **by the service** and the response carries the vector, dimension
   768, and the contract version string.
4. **Given** an input longer than the embedding model's window, **When** it is embedded, **Then** the
   truncation is reported in the response and in the log — never silently accepted.
5. **Given** any request, **When** it completes, **Then** exactly one structured log line records its
   id, endpoint, model, latency, and status.
6. **Given** the service's whole configuration surface, **When** it is inspected, **Then** it holds no
   source-database credential of any kind.
7. **Given** the endpoint's purpose is verification, **When** it returns, **Then** nothing has been
   written to any database.

---

### US3 — Boundaries that are executable, not remembered (Priority: P3)

Every forbidden table is refused **by its own name** in a test that lists all seventeen, so a future
change to the allowlist cannot quietly widen it. The two environment keys later phases depend on
exist now, generated locally and never committed.

**Why this priority**: it depends on nothing new and could be done any time — but it must be done
before P1 writes the first `student_ref`, and before P2 stores the first vector.

**Independent test**: run the guardrail tests with the model runtime and the service both stopped.

**Acceptance Scenarios**:

1. **Given** the seventeen forbidden table names, **When** each is requested through the source
   reader, **Then** each throws, naming that table.
2. **Given** the three write-blocking layers, **When** each is disabled in turn, **Then** the
   remaining layer still refuses the write.
3. **Given** the Lab schema after this increment's migrations, **When** it is inspected, **Then** no
   column is capable of holding personal data.
4. **Given** the committed environment templates, **When** they are inspected, **Then** every key
   this increment adds is listed with **no value**, and the real files are untracked.
5. **Given** the anonymisation pepper, **When** the repository is searched, **Then** its value appears
   nowhere in tracked content, and **no code in this increment consumes it**.

---

### Edge Cases Worth Naming

- **An aggregate health endpoint that hides a failure.** If one section throws and the aggregate
  returns a single error, the operator learns less than from three separate calls. Each section
  reports independently.
- **A health check that passes because it never ran.** A skipped check must be visibly skipped, and a
  skip must not produce exit 0 when the operator asked for the full matrix.
- **The two inverted checks read backwards.** "The write failed → PASS" is confusing at a glance.
  The output must state the expected direction on the line itself, or a future reader will "fix" a
  passing check into a broken one.
- **A vector round-trip that proves nothing.** Writing and reading back a vector of zeros, or reading
  back without comparing, tests the column type and not the extension. The value read must be
  compared to the value written.
- **The embedding prefix applied twice.** If the caller pre-applies the prefix and the service applies
  it again, the vectors are wrong and nothing errors. One owner: the service.
- **A contract string that drifts from what the service does.** If the version string claims a
  normalization the code does not perform, every downstream comparison is silently mis-scaled. The
  string must be derived from — or asserted against — the behaviour, not typed independently.
- **Chat health as a generation task.** The chat check must prove the model responds, not exercise a
  prompt. A prompt written here would be an unversioned prompt (Constitution IV) in a phase that has
  no prompt registry.
- **The health command as an attack surface on the source.** Check 9 deliberately attempts a write.
  It must attempt it through the guarded connection only, and its failure must be the layers
  refusing — never a syntax error or a missing table masquerading as a guard.
- **A pepper regenerated by accident.** Regenerating it after P1 has stored `student_ref` values
  breaks the link between old and new rows irreversibly. It is generated once, here, and backed up
  off-machine by the operator (§8 item F).
- **Ports 5000 and 7000 are taken** by ControlCenter (AirPlay) on this machine — hence 8001.

---

## Requirements

### The service (المرحلة 6)

- **FR-001**: A **stateless** service MUST exist under `apps/ai-service`, bound to loopback on port
  8001, and MUST refuse connections from any non-loopback address on this machine.
  The service is started **manually** for a work session and is neither a background agent nor a
  login item; the plan's "whole stack starts with one command" is المرحلة 11's, written beside the
  README that documents it (operator decision, 2026-08-22). This increment MUST NOT add a stack
  starter, a process supervisor, or a login item.
- **FR-002**: The service MUST expose four health endpoints, each reporting independently: liveness
  with its version; Lab database reachability; model runtime reachability with **both** model tags;
  and an aggregate that composes the three without masking any of them.
- **FR-003**: The service MUST NOT write to the Lab database, MUST NOT own or run any migration
  (Laravel owns every Lab migration — ADR-013), and MUST hold **no** InjazEdu source credential.
- **FR-004**: The service MUST expose a **verification-only** embedding endpoint that accepts text,
  applies the mandatory similarity prefix `task: sentence similarity | query: {text}`
  **server-side**, and returns the vector, its dimension, and the embedding contract version. It MUST
  persist nothing.
- **FR-005**: The **embedding contract** MUST be fixed in this increment as a single opaque version
  string covering model tag, prefix template, dimension, and normalization; it MUST be carried in the
  environment as `EMBEDDING_CONFIG_VERSION`, returned in every embedding response, and MUST NOT be
  changeable without invalidating stored vectors — a fact the committed template MUST state.
  The service MUST **L2-normalize to unit length itself** before returning, so the `l2norm`
  component of the contract is truthful independently of what the runtime returns and stays truthful
  across runtime upgrades (operator decision, 2026-08-22). A test MUST assert the returned vector's
  norm is 1 within floating-point tolerance — the contract string is proven, not merely typed.
- **FR-006**: Every returned vector MUST have dimension **768**. Matryoshka truncation to 512 is a P2
  decision that requires a recorded measurement first; this increment MUST NOT pre-empt it.
- **FR-007**: Text exceeding the embedding model's context window MUST have its truncation reported
  in both the response and the log. Silent truncation is a defect.
- **FR-008**: The service MUST NOT set a global context length. A context length of 4096 is a
  **per-call** parameter on generative calls, because the cost is KV-cache memory (§12.3).
- **FR-009**: The service MUST emit exactly one structured JSON log line per request carrying a
  request id, endpoint, model, latency in milliseconds, and status — so المرحلة 10's and §12.4's
  measurements are a matter of reading a log, not re-measuring by hand.
- **FR-010**: The service MUST have its own untracked environment file and a committed template
  listing every key with no values.
- **FR-011**: The service MUST contain no business logic, no similarity or duplicate scoring, no
  prompt, and no persistence of any kind.

### The health matrix (المرحلة 7)

- **FR-012**: A single command MUST run **ten** checks and report each on its own line:

  ```text
   1  Laravel  → Lab database (5433)
   2  Laravel  → the service (8001)
   3  Laravel  → queue            (a job dispatched AND executed by a worker)
   4  Service  → Lab database
   5  Service  → runtime, chat model
   6  Service  → runtime, embedding model (with the mandatory prefix)
   7  Lab DB   → store and read back one 768-dimension vector
   8  Laravel  → InjazEdu source, allowlisted tables
   9  Laravel  → attempted write to the source        ← MUST be refused
  10  Laravel  → source reader on a forbidden table   ← MUST throw
  ```

- **FR-013**: Checks 9 and 10 MUST invert: the check passes **only** when the operation is refused.
  A successful write or read MUST fail the check. The expected direction MUST be visible on the
  output line itself.
- **FR-014**: The command MUST exit non-zero if any check fails or is skipped, and MUST remain
  usable from a script. All of its output stays English (Constitution VI).
- **FR-015**: Every failing check MUST name the target it could not reach and the reason. A bare
  failure is a defect.
- **FR-016**: Check 3 MUST prove **execution**, not reachability: a job dispatched, executed by a
  worker, and its effect asserted after that worker has exited.
- **FR-017**: Check 7 MUST require a Lab-owned table with a 768-dimension vector column, created by a
  Laravel migration, written and read back **idempotently** — a fixed row, never an accumulating one
  — and the value read back MUST be compared to the value written.
- **FR-018**: Check 8 MUST report the question count together with the capture date of the data it
  counted (Constitution VI — numbers never travel alone).
- **FR-019**: The panel's stated placeholder page MUST be replaced by the real health result, with no
  fabricated status of any kind and no locale lock-in that would obstruct P1's Arabic + RTL surface.
  The page MUST run the ten checks **on demand** — showing no status until the operator asks, then
  the same ten results the command produces (operator decision, 2026-08-22). It MUST NOT persist a
  run, MUST NOT introduce a Lab table to store one, and MUST NOT run checks on page load.
- **FR-020**: The health command MUST NOT be the only place a guarantee is proven: the write-refusal
  and forbidden-table guarantees MUST also remain assertions in the test suite.

### The guardrails (المرحلة 8)

- **FR-021**: A test MUST assert that **each** of the seventeen forbidden tables is refused by name:
  `users`, `orders`, `course_order`, `book_order`, `coupons`, `certificates`, `complaints`,
  `complaint_responses`, `social_providers`, `personal_access_tokens`, `paymob_logs`, `zoom_users`,
  `audits`, `telescope_entries`, `google_oauth_tokens`, `failed_jobs`, `settings` — enumerated
  explicitly, so widening the allowlist cannot pass silently.
- **FR-022**: The three write-blocking layers MUST be re-proven to block **independently** after this
  increment's changes.
- **FR-023**: `STUDENT_REF_PEPPER` MUST be generated locally, held only in the untracked application
  environment file, listed with no value in the committed template, and **consumed by no code in this
  increment**. Its value MUST appear nowhere in tracked content.
- **FR-024**: `EMBEDDING_CONFIG_VERSION` and `SNAPSHOT_TAKEN_AT` MUST both be readable by the
  application and surfaced where they affect a reported result.
- **FR-025**: No Lab migration — including this increment's vector probe table — may create a column
  capable of holding personal data, and the existing assertion MUST still pass over the new schema.
- **FR-026**: The repository boundary check MUST still pass after the service and its dependency tree
  exist, and no new secret-bearing path may become committable.
- **FR-027**: Application-level guarantees MUST be proven by the framework's own test runner;
  infrastructure MUST be proven by shell scripts (`/bin/bash` is 3.2 — no bash 4+ syntax).

---

## Key Entities

- **Health check**: a named probe with a target, an **expected direction** (must succeed / must be
  refused), an outcome, and a human-readable detail. Ten of them form the matrix.
- **Embedding contract version**: one opaque string binding model tag, prefix template, dimension,
  and normalization. Stored with every vector from P2 onward; changing it invalidates every vector
  produced under the previous value.
- **Vector probe row**: a single fixed-id Lab row holding a 768-dimension vector, written and read
  back by check 7. Idempotent by construction; carries no data of any kind.
- **Anonymisation pepper**: the secret from which P1 derives `student_ref = HMAC-SHA256(pepper,
  user_id)`. Generated once, never committed, never stored in the Lab database, backed up off-machine
  by the operator.

---

## Success Criteria

- **SC-001**: The health command runs all ten checks and exits 0 with the full stack up.
- **SC-002**: Stopping any one dependency makes the affected checks — and only those — fail, each
  naming what it could not reach, with a non-zero exit.
- **SC-003**: Check 9 passes because a write to the source was refused, and check 10 passes because a
  forbidden table was refused by name. Making either operation succeed fails its check.
- **SC-004**: Zero rows are written to `injazedu` during this increment.
- **SC-005**: A 768-dimension vector is written to the Lab database and read back byte-identical,
  through a Laravel-owned migration, leaving exactly one probe row however many times it runs.
- **SC-006**: The service answers all four health endpoints on loopback and refuses every non-loopback
  connection attempt.
- **SC-007**: The embedding endpoint returns a 768-dimension vector and the contract version, with the
  prefix applied by the service — verified by embedding the same text with and without the prefix and
  observing different vectors.
- **SC-008**: Over-length input reports its truncation in both response and log.
- **SC-009**: Every service request produces exactly one structured log line with id, endpoint, model,
  latency, and status.
- **SC-010**: The service's configuration surface contains no InjazEdu source credential, and the
  service writes zero rows to the Lab database — verified by inspection and by an unchanged row count
  across a full health run.
- **SC-011**: All seventeen forbidden tables are refused by name, one assertion each.
- **SC-012**: Each of the three write-blocking layers still refuses alone.
- **SC-013**: The pepper exists in the untracked environment file, appears with no value in the
  committed template, is absent from all tracked content, and is referenced by no code.
- **SC-014**: The panel page shows no status until the operator runs the checks, then shows the same
  ten results as the command — nothing fabricated, nothing persisted, and no check run on page load.
- **SC-016**: Every returned vector has an L2 norm of 1 within floating-point tolerance, matching the
  normalization the contract string claims.
- **SC-015**: The repository boundary check and the no-PII schema assertion both still pass.

---

## Assumptions

Measured on this machine, 2026-08-22 unless noted:

- Port **8001** is free; 5000 and 7000 are held by ControlCenter (AirPlay).
- Ollama **0.32.15** is running on `127.0.0.1:11434` with both tags present:
  `gemma4:e2b-it-qat` and `embeddinggemma:300m-qat-q4_0`. Combined resident allocation measured
  **3,669.2 MiB**, with a conservative process-tree RSS of **4,673 MiB** — below the 13,312 MiB
  ceiling, so no runtime limits are pinned (notes N5).
- **Load order matters** on this 16 GB unified-memory Mac: loading the embedding model first causes
  the scheduler to evict it when the chat model loads. The larger model loads first.
- `uv` **0.10.12** and Python **3.13.7** are installed. `asyncpg`, `httpx`, `fastapi`, `uvicorn`, and
  `pydantic` are the service's declared dependencies.
- PHP **8.2.27** is linked and 31 local projects depend on it; **8.4.2** at
  `/opt/homebrew/opt/php@8.4/bin/php` runs this application. Never `brew link`.
- The Lab database (PostgreSQL 17 + pgvector 0.8.6 on 5433) is running, with `vector` and `pg_trgm`
  installed; the source connection, the queue, the panel, and the `lab` log channel all exist and
  pass their verifications (002).
- `/bin/bash` is **3.2** — no bash 4+ syntax in any script.
- EmbeddingGemma's context window is **2K tokens**; longer text is truncated by the runtime, which is
  why FR-007 requires that truncation be reported rather than inferred.
- The embedding contract's normalization component is **L2 to unit length, performed by the
  service**, and is asserted against the returned vector rather than assumed of the runtime.

---

## Dependencies

- `002-snapshot-access-and-runtime` accepted, with its verifications still passing.
- The Lab database container, the model runtime, and MySQL all running on loopback. Network access
  for Python package downloads only.
- No connection to `injazedu.co` or any remote environment, in this or any other increment.

---

## Handoff to المرحلة 9

```text
One command that proves ten connections, two of them by refusing
A stateless service on loopback that is the only door to the model runtime
An embedding contract fixed before the first vector exists
A pgvector round-trip proven end to end — which is what المرحلة 9's restore
  drill must reproduce after a restore to count as verified
Seventeen forbidden tables refused by name
STUDENT_REF_PEPPER generated and uncommitted, waiting for P1
```

Still open by design: nothing is backed up yet, and no restore has been attempted — which is why
المرحلة 9 is next and why P0 cannot be accepted before it runs.
