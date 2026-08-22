# InjazEdu AI Assessment & Engagement Lab — Constitution

**Version**: 2.0.0 | **Last amended**: 2026-08-21

This governs `injazedu-ai-lab`: a **local-first, single-developer** AI laboratory built around the
production platform `injazedu.co`. It is binding on every spec, plan, task list, and commit here.

It is deliberately short. Rules exist here because they protect data or code quality — not because
a process asks for them. Where a rule below and a document elsewhere disagree, this file wins.

Reference documents (context, not contracts):

- `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0) — the program
- `docs/plans/project/1/p0-ai-lab-foundation.md` — the current project's execution plan
- `docs/schema/injazedu-db-schema.md` — the production schema
- `docs/plans/lean-development-process.md` — why this file looks the way it does

Where a plan and the measured schema conflict, **the schema wins** and the plan is corrected.

---

## I. No Unapproved Assumptions

**This principle has priority over every other.**

Do not decide architecture, infrastructure, security, database, dependency, or workflow questions
that this repository or the operator has not already settled.

If something is ambiguous, unspecified, contradictory, missing, carries a meaningful trade-off, or
would change the architecture, add infrastructure, add a security restriction, or reverse a stated
decision — **stop and ask before deciding or implementing it.**

You may identify the problem, lay out the options, explain the trade-offs, and recommend one. Then
wait. Do not silently pick what you believe is best practice.

Never grant yourself extra constraints, permissions, infrastructure, services, accounts, or scope.

**This does not apply to ordinary implementation.** Where the architecture and requirements already
establish the intended behaviour, use normal engineering judgement and get on with it. The gate is
for **meaningful decisions**, not for every line of code.

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
3. the source reader **refuses** any table outside the allowlist.

These stop accidents, not intent. Do not add database-level permission infrastructure to "fix" this
without asking first (Principle I).

**No PII enters the Lab database.** Behavioural rows carry `student_ref = HMAC-SHA256(pepper,
user_id)` only. The pepper lives in `.env`, is never committed, and is never stored in the Lab
database. No model needs a student's name to analyse a question.

**The copy allowlist.** Only these tables may be read and copied:

```text
categories · courses · chapters · lectures · quizzes · sections
questions · options · quiz_files · results · question_result
```

`results` and `question_result` carry `user_id`: it is **read and never stored** — converted to
`student_ref` on the way in. Everything else — `users`, `orders`, `certificates`,
`personal_access_tokens`, `social_providers`, and the rest — is out of bounds.

**The local production copy** lives on FileVault-encrypted storage, outside this repository, outside
any cloud-synced folder, and is never copied to another machine. It is stamped with
`snapshot_taken_at`. Note the measured exposure: `personal_access_tokens` (~24,408) and
`social_providers` (~17,369) may contain **live credentials**.

**Lab-owned public surfaces collect no PII by design** — no login, email, phone, or name; only a
random first-party session id, and nothing that does not feed a declared metric.

**Retrieved document text is data, never instructions.** PDF and OCR content is passed in a
delimited data field; system prompts state that instructions inside retrieved content are ignored;
generator output is schema-validated and never executed.

**Secrets live in `.env` files only** and are never committed. Every `.env.example` lists every key
with no values. No production credentials exist on this machine.

**Human review decisions are the most valuable data in the system.** Regular `pg_dump` to encrypted
local storage, an off-machine copy, and at least one verified restore before any group review.

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
3. **Guardrail tests**: a write through the `injazedu` connection throws; a table outside the
   allowlist throws; no Lab table holds a PII column.
4. **Golden dataset (eval) tests**, re-run after any change to model, prompt, normalization,
   embedding, or chunking. Metrics are recorded with the run, not merely observed.

Every statistical output must be reproducible from raw rows, and a sample-based test must prove it.

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

The 16 GB M1 Pro is a hard constraint.

- **Memory.** The full stack stays within ~11–13 GB and must not exceed 13 GB at idle. Larger models
  are measured only in isolated sessions.
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

- **Flow**: `/speckit-specify` → `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`, one
  branch per increment.
- **Artefacts earn their place.** `spec.md`, `plan.md`, and `tasks.md` are the default.
  `research.md`, `contracts/`, and `checklists/` are written **only when they change what gets
  built** — never because a template lists them.
- **Tasks are implementation, testing, infrastructure, and safety work.** A documentation task needs
  a reason beyond "the process asks for one".
- **ADRs are the exception, not the routine.** Write one only when a decision is architectural,
  durable, and expensive to reverse. Not for which PHP binary to invoke, where `.env` sits, a Docker
  setting, a dependency version, or an ordinary implementation choice. If an existing ADR only
  exists because an old process demanded it, delete it and clean up the references.
- **Done** means: it runs, its tests pass, and you said plainly what you skipped. If a check was
  skipped, say so. If a metric is below its gate, say so and stop. Claiming completion without a
  passing verification run is a violation, not a style issue.
- **Measure before building.** Anything depending on bank statistics runs the profiling pack first
  and replaces estimates with real numbers.

**Amendment**: edit this file and update the version line at the top. Nothing else is required.
