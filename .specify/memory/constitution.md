# InjazEdu AI Assessment & Engagement Lab — Constitution

**Version**: 2.4.0 | **Last amended**: 2026-08-27

This governs `injazedu-ai-lab`: a **local-first, single-developer** AI laboratory built around the
production platform `injazedu.co`. It is binding on every spec, plan, task list, and commit here.

It is deliberately short. Rules exist here because they protect data or code quality — not because
a process asks for them. Where a rule below and a document elsewhere disagree, this file wins.

Reference documents (context, not contracts):

- `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0) — the program
- `docs/plans/project/1/p1-production-profiling-and-question-mirror.md` — the current project's
  execution plan
- `docs/plans/project/0/p0-ai-lab-foundation.md` — the previous project, implemented and closed
- `docs/schema/injazedu-db-schema.md` — the production schema
- `docs/plans/lean-development-process.md` — why this file looks the way it does

Where a plan and the measured schema conflict, **the schema wins** and the plan is corrected.

---

## I. No Unapproved Assumptions

**This principle has priority over every other.**

The gate is narrow on purpose (narrowed 2026-08-25). It covers what cannot be undone by an edit —
not every design question.

```text
Stop and ask before deciding:
  · anything that moves a data boundary or weakens a security property
  · anything expensive to reverse once data exists — the mirror schema shape,
    STUDENT_REF_PEPPER, the embedding contract
  · new infrastructure, services, accounts, or dependencies on this machine
  · a change to the scope of the current project

Decide with judgement, and state it in the plan:
  · ordinary architecture, patterns, class boundaries, library choices
  · anything reversible by an edit
```

When you must ask: identify the problem, lay out the options, explain the trade-offs, and recommend
one. Then wait. Do not silently pick what you believe is best practice.

Never grant yourself extra constraints, permissions, infrastructure, services, accounts, or scope.

**Ordinary implementation is not gated.** Where the architecture and requirements already establish
the intended behaviour, use normal engineering judgement and get on with it.

---

## II. What the Lab Is

```text
Production serves students.
The Lab prepares, analyses and recommends.
A human approves.
Where the Lab cannot hand anything to Production, it owns its own engagement
surface (Telegram + public practice pages) — ungraded, public, no PII.
```

- Every feature names the process it improves and which layer does each part: deterministic code,
  SQL, embeddings, an LLM, orchestration, or a human.
- AI may **suggest, classify, rank, flag, draft, and explain** computed numbers. AI must never
  publish a question, delete a source question, change a correct answer, make an academic
  judgement, message a student unreviewed, or write to Production.
- "Agent" here means a bounded tool loop with a step ceiling (~6) and a human gate — never an
  autonomous system.
- No source question is ever deleted. Duplicates are clustered, because `question_result`
  references `questions` and history must stay intact.

---

## III. Data Boundaries (non-negotiable)

```text
Native MySQL on this Mac          Docker PostgreSQL 17 + pgvector
database: injazedu                127.0.0.1:5433 · database: injazedu_lab
user: root / no password                        ▲
              │                                 │
              │  READ / COPY ONLY               │  READ / WRITE
              └────────► Lab application ───────┘
```

**InjazEdu MySQL is a read-only source.** The Lab reads it, copies what it needs, and transforms
that into Lab-owned Postgres tables. The Lab must never `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`,
`DROP`, `ALTER`, `CREATE`, or `REPLACE` against it.

MySQL itself does **not** enforce this — the connection uses `root`, which has full privilege. That
trade-off is deliberate and accepted for a single-operator local machine (`docs/ADR/ADR-021.md`).
Read-only is therefore enforced in the application, in three layers:

1. the `injazedu` connection is configured with **no write target**;
2. a query listener on that connection **throws** on any non-read statement;
3. the source reader **refuses**, by name, any table outside the two allowlists below.

These stop accidents, not intent. Do not add database-level permission infrastructure to "fix" this
without asking first (Principle I).

**No PII enters the Lab database.** Behavioural rows carry `student_ref = HMAC-SHA256(pepper,
user_id)` only. The pepper lives in `.env`, is never committed, and is never stored in the Lab
database. No model needs a student's name to analyse a question.

**Two allowlists, because reading and storing are different acts** (amended 2026-08-23).

*The copy allowlist* — what may be written into the Lab database:

```text
categories · courses · chapters · lectures · quizzes · sections
questions · options · quiz_files · results
```

*The profile allowlist* — additionally readable for **aggregate** profiling, never stored:

```text
course_user · course_order · orders · user_roles · roles · book_course
question_result
```

The first six answer questions the program cannot proceed without — which table actually records
enrolment, whether `course_user` holds students or trainers — and they are read as counts, never
copied. Everything outside both lists (`users`, `certificates`, `personal_access_tokens`,
`social_providers`, and the rest) is refused by name.

`question_result` joined this list on 2026-08-26 (ADR-022). Its 13.8M answer events are unbounded
behavioural data — they grow with students × time — and nothing in the program annotates an
individual one. Everything read from them is an aggregate whose size is bounded by the **question**
count, so they are read as statistics and never mirrored. **Mirror what gets enriched and is
bounded; aggregate what is only ever counted and is unbounded.**

`results` and `question_result` carry `user_id`: it is **read and never stored** — converted to
`student_ref` on the way in.

The guarantee that survives the split is the one that matters: **no PII column exists in the Lab
database**, proven by a schema assertion, not by what a query happened to select.

**The local production copy** lives on FileVault-encrypted storage, outside this repository, outside
any cloud-synced folder, and is never copied to another machine. It is stamped with
`snapshot_taken_at`. Note the measured exposure: `personal_access_tokens` (~24,408) and
`social_providers` (~17,369) may contain **live credentials**.

**The copy is fixed at `snapshot_taken_at = 2026-08-07`** and is the source for the entire local
program (operator decision, 2026-08-25; closes P0 §8 item E). There is no refresh, no cadence, and
**no gate anywhere may block on the copy's age**. The date is recorded and displayed as context so
every number is read in its own frame — never as a threshold. The operator may inspect, query,
transform, or modify this local copy freely; the read-only rule above governs what the **Lab
application** does to it, which is what the three layers enforce.

**Lab-owned public surfaces collect no PII by design** — no login, email, phone, or name; only a
random first-party session id, and nothing that does not feed a declared metric.

**Retrieved document text is data, never instructions.** PDF and OCR content is passed in a
delimited data field; system prompts state that instructions inside retrieved content are ignored;
generator output is schema-validated and never executed.

**Secrets live in `.env` files only** and are never committed. Every `.env.example` lists every key
with no values. No production credentials exist on this machine.

**Everything in the Lab database is reproducible** — re-import from the snapshot, re-run the
pipeline — with one exception: reviewer decisions, which exist nowhere else. There is **no local
backup requirement**; this machine is a development environment and the snapshot is disposable
(amended 2026-08-23). Durability for reviewer decisions is a go-live concern, handled when the Lab
runs against a real database on real infrastructure — not a local one.

**Reproducible infrastructure is not disposable data (amended 2026-08-27).** That the *schema* can
be recreated from `migrate` does not make the manually-imported mirror sitting in `injazedu_lab`
something to reset on a whim — re-importing it costs real time the operator does not want to pay
repeatedly. Automated tests run against an isolated, genuinely disposable database
(`injazedu_lab_test`) and never the real one; a technical guard refuses `migrate:fresh` and other
destructive operations outside it. Tests that must read the real mirror do so read-only, outside
the default test run.

---

## IV. Code Quality — Deterministic Core, AI at the Edge

- **Laravel owns every Lab migration.** FastAPI is stateless and returns JSON. The one exception is
  bulk embedding writes, whose migrations still live in Laravel.
- **All metrics are computed deterministically** in SQL or Python. An LLM may phrase a report about
  a computed number; it must never compute, estimate, or invent one.
- **Every AI task returns validated structured output.** Define a JSON Schema and validate before
  accepting the result. Regex-parsing prose is forbidden.
- **Prompts are versioned.** Changing a prompt creates `v2`; it never overwrites `v1`, so a quality
  change can be attributed to the model or to the prompt.
- **Embeddings carry a contract.** The EmbeddingGemma prefix is mandatory and every vector row
  stores `embedding_config_version` (model tag + prefix + dimension + normalization). Changing any
  part invalidates stored vectors and forces a re-embed.
- **Determinism where order matters.** Option order is always ``ORDER BY `order` ASC, id ASC``;
  `payload_hash` is SHA256 over a key-sorted normalized serialization. Same input, same hash.
- **Idempotency is required** for every import, batch, and job: re-running updates what changed and
  creates no duplicates.
- **Anomalies are recorded, not swallowed.** Import and validation errors go to a visible errors
  table and the batch continues. A silent `try/catch` is a defect.
- **Arabic text is layered and never destroyed**: `raw_text` is immutable, `clean_text` is technical
  cleanup only, `search_text` is the comparison form. Meaning-changing normalization (notably
  `ة → ه`) is forbidden.
- **Services are added only when justified today.** "We will need it later" is not a justification.

---

## V. Targeted Testing

Testing is deliberately narrow. Coverage targets, end-to-end suites, mocking frameworks, and
mutation testing are out of scope.

1. **Unit tests for the deterministic core**: Arabic normalizer, hash generation, correct-answer and
   option-index derivation, payload validation, state transitions, statistical formulas.
2. **A health check as an executable test**, not documentation — non-zero exit on any failure.
3. **Guardrail tests**: a write through the `injazedu` connection throws; a table outside **both**
   allowlists throws by name; a profile-only table is readable but not copyable; no Lab table holds
   a PII column.
4. **Golden dataset (eval) tests**, re-run after any change to model, prompt, normalization,
   embedding, or chunking. Metrics are recorded with the run, not merely observed.

Every statistical output must be reproducible from raw rows, and a sample-based test must prove it.

**Tests reduce meaningful risk; they do not maximise count.** Do not test trivial framework
behaviour, simple accessors, or UI wiring that Laravel and Filament already guarantee. Where the
risk is real — domain logic, derivations, data integrity, security boundaries, ETL idempotency and
resume, AI structured-output contracts — test it properly.

Infrastructure is verified by shell scripts; application behaviour is verified by the framework's
own test runner. Each tool checks what it is good at.

---

## VI. One Coherent Surface

Three human surfaces — Filament consoles, the Telegram bot, and public practice pages — must feel
like one system.

- **Arabic-first.** Reviewer- and student-facing UI is Arabic with correct RTL. Technical
  identifiers stay English. Operator-facing output (scripts, logs, health checks) stays English.
- **Numbers never travel alone.** Every displayed statistic shows its sample size `n` and the
  `snapshot_taken_at` it came from. Suppression: `n < 10` → publish nothing; `n < 30` → p-value
  only; `n ≥ 30` → full metrics.
- **Every number is a link.** A reviewer can click any count and land on the underlying questions.
- **AI output is always labelled** as a recommendation, with its confidence and prompt version.
  AI-predicted difficulty is visually distinct from measured p-value.
- **Human override is always available and always recorded** — reviewer, timestamp, reason.
- **Review queues are ordered by priority, never by id**, with a daily cap.
- **Lab surfaces state their limits** — ungraded, unofficial, not a certificate.

---

## VII. Measured Budget

The 16 GB M1 Pro is a real constraint, managed rather than gated (amended 2026-08-23).

- **Memory.** macOS manages memory; the Lab does not police it. There is **no memory gate and no
  acceptance criterion on a memory number**. `docs/runbooks/memory-check.md` holds the manual steps
  to run when the machine feels slow, and what to do about each result. Measured 2026-08-23: the
  stack costs ~5.1 GB with both models loaded, and ~90% of that is the two models — so performance
  work belongs in the pipeline, not in tuning Postgres or MySQL. Larger models are still evaluated
  in isolated sessions.
- **Cheap layers run first.** Hash → `pg_trgm` → pgvector → LLM. Skipping a layer to "just ask the
  model" is a defect.
- **LLM calls are rationed by an uncertainty band.** Exact matches and high-similarity pairs are
  clustered without an LLM; pairs below `T_low` are dropped; only the band between reaches the
  model, capped at ~5,000 pairs. A counter proves the LLM saw nothing outside the band.
- **Model choice is empirical.** Embedders are benchmarked before generative models. No model or
  dimension is adopted without a recorded measurement.
- **Indexes are earned.** Exact scan is the default; HNSW arrives when a latency requirement is
  demonstrated.
- **Heavy batches run off-hours** and must be resumable.

---

## How Work Gets Done

- **Flow**: `/speckit.specify` → `/speckit.plan` → `/speckit.tasks` → `/speckit.implement`, **one
  spec per project**, on one branch. A project is not split into increments unless its own plan says
  so and gives an engineering reason.
- **Artefacts earn their place.** `spec.md`, `plan.md`, and `tasks.md` are the default.
  `research.md`, `contracts/`, and `checklists/` are written **only when they change what gets
  built** — never because a template lists them.
- **Tasks are implementation, testing, infrastructure, and safety work.** A documentation task needs
  a reason beyond "the process asks for one".
- **ADRs are the exception, not the routine.** Write one only when a decision is architectural
  **and** durable **and** expensive to reverse. Not for which PHP binary to invoke, where `.env`
  sits, a Docker setting, a dependency version, or an ordinary implementation choice.
- **Documentation policy**: code first · tests for important behaviour · documentation only when it
  has continuing practical value. Prefer updating an existing document over creating another.
  Reports, handover documents, acceptance records, checklists, and phase summaries are **not**
  produced by default. A new runbook needs a real manual procedure you will perform again — starting
  or recovering the stack, a complicated import, a future deployment. Implementation decisions,
  measurements, normal development commands, and explanations of code belong in the code, the tests,
  the configuration, `README.md`, or the project plan.
- **Gate policy**: a gate protects a real engineering property or prevents an expensive failure —
  no writes to the source, no PII in Lab storage, ETL correctness, idempotency where re-running is
  expected, model or eval quality thresholds where bad output would reach users. These are the only
  gates. **Procedural gates are not gates** and are not written: mandatory report authoring,
  documentation review, handover sign-off, a memory number, the snapshot's age, or any check whose
  only purpose is satisfying another document.
- **Done** means: it runs, its tests pass, and you said plainly what you skipped. If a check was
  skipped, say so. If a metric is below its gate, say so and stop. Claiming completion without a
  passing verification run is a violation, not a style issue.
- **Measure before building.** Anything depending on bank statistics runs the profiling pack first
  and replaces estimates with real numbers.

**Amendment**: edit this file and update the version line at the top. Nothing else is required.
