<!--
SYNC IMPACT REPORT
==================
Version change: (unversioned template) → 1.0.0
Bump rationale: Initial ratification. The file previously contained only placeholder
tokens; this is the first concrete adoption of governance for this repository.

Principles defined (7 — user requested 7 focus areas; template's 5 slots expanded):
  - (new) I.   Core Idea — Prepare, Recommend, Human Approves
  - (new) II.  The Plans Are the Specification
  - (new) III. Project-by-Project Delivery
  - (new) IV.  Code Quality — Deterministic Core, AI at the Edge
  - (new) V.   Basic Testing Only
  - (new) VI.  User Experience Consistency
  - (new) VII. Performance Within a Measured Budget

Sections added:
  - Data Protection & Production Boundary (NON-NEGOTIABLE)  [was SECTION_2_NAME]
  - Development Workflow & Quality Gates                    [was SECTION_3_NAME]
  - Governance

Sections removed: none (all template placeholders resolved).

Templates and guidance propagated:
  ✅ .specify/templates/plan-template.md      — Constitution Check placeholder replaced with 8 concrete gates
  ✅ .specify/templates/tasks-template.md     — "Constitution Alignment" section added (test scope, scope
                                                boundaries, schema/AI-output/UX task requirements)
  ✅ CLAUDE.md, AGENTS.md                     — governance pointer added outside the SPECKIT block
  ✅ .specify/templates/spec-template.md      — reviewed; user-story/requirements structure needs no change
  ✅ .specify/templates/checklist-template.md — reviewed; generic, no change needed
  ✅ .claude/skills/speckit-*/SKILL.md        — reviewed; agent-generic wording, no outdated references

Deferred TODOs: none.
-->

# InjazEdu AI Assessment & Engagement Lab Constitution

This constitution governs `injazedu-ai-lab`: a local-first AI laboratory built around the
production platform `injazedu.co`. It is binding on every specification, plan, task list,
and commit in this repository.

Authoritative source documents:

- `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0) — governing plan
- `docs/plans/core/injazedu_ai_assessment_engagement_lab_full_plan.md` (v1.0) — superseded; principles only
- `docs/schema/injazedu-db-schema.md` — the production reality
- `docs/plans/project/N/*.md` — per-project execution plans

Where v1.0 and v2.0 conflict, **v2.0 wins**. Where any plan and the measured schema conflict,
**the schema wins** and the plan is amended.

## Core Principles

### I. Core Idea — Prepare, Recommend, Human Approves

The program has one shape, and every feature MUST fit inside it:

```text
Production serves students.
AI Lab prepares, analyses and recommends.
Humans approve.
Where the Lab cannot hand anything to Production, the Lab owns its own
engagement surface (Telegram + public practice pages) — ungraded, public, no PII.
```

Non-negotiable rules:

- The Lab MUST NOT be framed as "adding AI to InjazEdu". Every feature MUST name the process
  it improves and state which part is executed by deterministic code, by SQL, by embeddings,
  by an LLM, by orchestration, or by a human (v1.0 §56).
- AI MAY suggest, classify, rank, flag, draft, and explain computed numbers. AI MUST NOT
  publish questions, delete source questions, change a correct answer, make academic
  judgements, message students unreviewed, or write to Production (ADR-005, v1.0 §25).
- "Agent" in this program means a bounded tool loop with a step ceiling (~6) and a mandatory
  human gate, never an autonomous system (ADR-014). Anything else MUST be rejected in review.
- The two declared goals — question quality and **engagement** — run on parallel tracks.
  Engagement work MUST NOT be blocked behind the full completion of the data track.
- No source question is ever deleted. Duplicates are clustered, not removed, because
  `question_result` references `questions` and history MUST stay intact.

**Rationale:** v1.0 promised outcomes the schema cannot support. Fixing the frame first is
what makes every downstream promise honest and measurable.

### II. The Plans Are the Specification

The plan documents are not background reading; they are the contract.

- Every spec and plan MUST cite the plan section it implements (e.g. "implements §17 P2").
- Any deviation from a governing plan section MUST be recorded as a **numbered ADR** in
  `docs/ADR/` before the code is written, stating the deviation, the reason, and the impact.
  ADR-001…ADR-015 (core plan) and ADR-016…ADR-020 (P0) are already in force.
- Numbers in the plans that were estimates MUST be replaced by measured numbers once measured,
  and the plan section MUST be updated in the same change (the bank is **29,142** questions,
  not ~25,000; snapshot date `2026-08-07`).
- Assumptions about the production schema are forbidden. Every mapping MUST be traceable to
  `docs/schema/injazedu-db-schema.md` or to a profiling query result. In particular: there is
  no `explanation` column, no answer-key column (correct = `options.points > 0`), no
  question↔exam many-to-many, and no per-question timing.
- Work not described in a plan and not covered by an ADR MUST NOT be built.

**Rationale:** v1.0 was written before the schema was read and produced impossible commitments.
Traceability from code to plan to schema is the mechanism that prevents a repeat.

### III. Project-by-Project Delivery

The program is built as a sequence of self-contained projects (P0 → P9), each with its own
scope, out-of-scope list, deliverables, effort estimate, Go/No-Go limits, and acceptance
criteria.

- Exactly **one** project is active at a time. Its declared dependencies MUST be accepted
  before it starts (P1 requires P0; P2 requires P1; P3 requires P1 stage 2; P4 requires P1+P2;
  P5 requires P1+P2 plus human-authored taxonomy).
- A project is not done until **every** acceptance-criteria checkbox in its plan is checked
  and demonstrable. Partial completion MUST be reported as partial.
- The out-of-scope list is binding. Writing another project's logic inside the current project
  is a defect, not a head start ("if you find yourself writing business logic in P0, you are
  in the wrong project").
- Go/No-Go gates MUST halt work rather than be negotiated. Examples in force:
  `correct_count = 0` above 2% of the bank stops the dedup track and makes broken-key repair
  the first deliverable; dedup below precision ≥ 0.90 at recall ≥ 0.70 on the 400-pair set does
  not run against the full bank; AI quality flags below 0.80 agreement with human labels are
  not shown to trainers.
- Each project MUST leave a written handoff: what the next project inherits, and which numbers
  it MUST recompute.

**Rationale:** Ten projects on one 16 GB machine with scarce human reviewers only converge if
each one finishes, proves itself, and hands over cleanly.

### IV. Code Quality — Deterministic Core, AI at the Edge

Ownership boundaries are structural, not stylistic:

- **Laravel owns every Lab migration.** FastAPI is stateless and returns JSON. The single
  declared exception is bulk embedding writes to `question_embeddings` and
  `document_chunk_embeddings`, whose migrations still live in Laravel (ADR-013).
- **All metrics are computed deterministically** in SQL or Python. An LLM MAY phrase a report
  about a computed number; it MUST NEVER compute, estimate, or invent one (ADR-009).
- **Every AI task returns validated structured output.** A JSON Schema MUST be defined and
  validated before the result is accepted. Regex-parsing prose is forbidden (v1.0 §24).
- **Prompts live in a versioned registry**, exported to Git for review. Changing a prompt
  creates `v2`; it never overwrites `v1`, so a quality change can be attributed to the model
  or to the prompt (v1.0 §23).
- **Embeddings carry a contract.** The EmbeddingGemma prefix is mandatory and every vector row
  MUST store `embedding_config_version` (model tag + prefix template + dimension +
  normalization). Changing any part invalidates stored vectors and MUST force a re-embed.
- **Determinism where order matters.** Option order is always
  ``ORDER BY `order` ASC, id ASC``; `payload_hash` is SHA256 over a key-sorted normalized
  serialization. Same input MUST produce the same hash on every run.
- **Idempotency is required** for every import, batch, and job: re-running updates what changed
  and creates no duplicates.
- **Anomalies are recorded, not swallowed.** Import and validation errors go to a visible
  `import_errors`-style table and the batch continues; silent `try/catch` is a defect.
- **Arabic text is layered and never destroyed**: `raw_text` is immutable, `clean_text` is
  technical cleanup only, `search_text` is the comparison form. Meaning-changing normalization
  (notably `ة → ه`) is forbidden (ADR-007).
- Secrets live only in `.env`; `.env.example` MUST list every key. No production credentials
  exist on the development machine.
- Services are added only when justified **today**. Redis (ADR-011) and n8n (ADR-012) stay out
  until their named trigger project. "We will need it later" is not a justification.

**Rationale:** The expensive failures in this system are silent ones — a drifted schema, a
changed prefix, a non-reproducible hash. These rules make those failures loud.

### V. Basic Testing Only

Testing is deliberately lightweight and targeted. Broad coverage targets, end-to-end suites,
mocking frameworks, and mutation testing are explicitly **out of scope**.

Required, and nothing beyond it without an ADR:

1. **Unit tests** for the deterministic core only: Arabic normalizer, hash generation,
   correct-answer and option-index derivation, payload validation, state transitions,
   statistical formulas (v1.0 §47).
2. **Integration health checks** as executable tests, not documentation: `php artisan lab:health`
   MUST verify all ten connections, including the two inverted checks where **success is
   failure** — a write to the MySQL snapshot MUST fail, and a `SELECT` on `users` MUST fail
   (proving ADR-020). Exit code MUST be non-zero on any failure.
3. **Guardrail tests**: every table on the §14.2 forbidden list is unreadable by `lab_ro`;
   removing any `GRANT` MUST break a test; no PII column can reach a Lab table.
4. **Golden dataset (eval) tests**, re-run after any change to model, prompt, normalization,
   embedding, or chunking. Metrics (precision, recall, human agreement) MUST be recorded and
   published with the run, not merely observed.

Every statistical output MUST be reproducible from raw rows; a sample-based test MUST prove it.

**Rationale:** A one-developer lab cannot afford a test pyramid, but it also cannot afford an
undetected key-derivation bug or a PII leak. Test the things that are silent and expensive;
skip the rest.

### VI. User Experience Consistency

There are three human surfaces — Filament review consoles (moderators/trainers), the Telegram
bot, and public practice pages — and they MUST feel like one system.

- **Arabic-first.** All reviewer- and student-facing UI is Arabic with correct RTL layout.
  Technical identifiers stay in English. Mixed content MUST not break direction or alignment.
- **Numbers never travel alone.** Every displayed statistic MUST show its sample size `n` and
  the `snapshot_taken_at` it was computed from. Suppression thresholds are absolute:
  `n < 10` → publish nothing; `n < 30` → p-value only; `n ≥ 30` → full metrics.
- **Every number is a link.** A reviewer MUST be able to click any count or metric and land on
  the underlying questions. Dead-end dashboards are rejected.
- **AI output is always labelled.** Any AI-produced verdict, flag, classification, or draft is
  visibly marked as a recommendation with its confidence and prompt version. AI-predicted
  difficulty MUST be visually distinct from measured p-value.
- **Human override is always available and always recorded** with reviewer identity, timestamp,
  and reason. An AI verdict is never final.
- **Review queues are ordered by priority, never by id**, with a declared daily cap so
  reviewers are not flooded. Conflicting-key items jump the queue.
- **Review screens share one layout**: side-by-side comparison, options with derived correct
  answer, similarity and statistics in the same positions, and the same action verbs
  everywhere.
- **Lab surfaces state their limits.** Public practice and Telegram MUST show that they are
  ungraded, unofficial, and not a certificate; conversion reports MUST state that the funnel
  is measurable only up to the outbound click.

**Rationale:** Reviewer time is the scarcest resource in the program (30–60 hours total), and
trust is lost permanently the first time an unreliable flag is shown as if it were fact.

### VII. Performance Within a Measured Budget

The 16 GB M1 Pro is a hard constraint, not context. Capacity is designed, then verified.

- **Memory budget.** The full stack MUST stay within ~11–13 GB and MUST NOT exceed 13 GB at
  idle. `gemma4:e2b-it-qat` is the working model; larger models are measured only in isolated
  sessions with Docker, browsers, and workers stopped. Ollama runs with
  `OLLAMA_MAX_LOADED_MODELS=2`, `OLLAMA_NUM_PARALLEL=1`, `OLLAMA_KEEP_ALIVE=5m`, and
  `num_ctx=4096` (a KV-cache decision, not frugality).
- **LLM calls are rationed by an uncertainty band.** Exact hash matches and high-similarity
  pairs are clustered without an LLM; pairs below `T_low` are dropped; **only** the band
  between `T_low` and `T_high` reaches the model. The band MUST be capped at ~5,000 pairs
  (≈ 6 hours, overnight). A counter MUST prove the LLM saw nothing outside the band. If the
  band exceeds 8,000 pairs, thresholds are tightened before the run.
- **Cheap layers run first.** Hash → `pg_trgm` → pgvector → LLM. Skipping a layer to "just ask
  the model" is a defect.
- **Model choice is empirical.** Embedders are benchmarked **before** generative models, because
  recall@20 on Arabic duplicate pairs cannot be recovered downstream. No model or dimension
  (including Matryoshka 512) is adopted without a recorded measurement.
- **Indexes are earned.** ~50,000 × 768-dim vectors ≈ 154 MB; exact scan is the default. HNSW
  (`m=16`, `ef_construction=64`) is added only when an interactive latency requirement is
  demonstrated.
- **Human throughput is budgeted like compute.** Review work is scheduled in slots against the
  §13.3 budget, and active learning MUST be used to cut review volume once ~150 pairs are
  labelled.
- **Heavy batches run off-hours** and MUST be resumable; a failed batch never restarts from zero.

**Rationale:** "33 hours of continuous LLM inference" is what an unbudgeted design costs here.
Explicit budgets are what make each project finite instead of open-ended.

## Data Protection & Production Boundary (NON-NEGOTIABLE)

These constraints outrank convenience, deadlines, and any principle above.

**Production is read-only for this entire program.** No migrations, no code changes, no writes
to `injazedu.co`, and no live connection from the Lab. `PRODUCTION_WRITE_ENABLED=false` exists
as a kill switch from day one and MUST be checked by any future write path.

**The allowlist is enforced by database grants, not by discipline (ADR-020).** The `lab_ro`
MySQL user holds `SELECT` on exactly eleven tables:

```text
categories · courses · chapters · lectures · quizzes · sections
questions · options · quiz_files · results · question_result
```

Everything else — `users`, `orders`, `course_order`, `book_order`, `coupons`, `certificates`,
`complaints`, `complaint_responses`, `social_providers`, `personal_access_tokens`,
`paymob_logs`, `zoom_users`, `audits`, `telescope_*`, `google_oauth_tokens`, `failed_jobs`,
`settings` — MUST fail at the permission layer before a row is read. The automated PII test is
a second layer, never the only one.

**No PII enters the Lab database.** Behavioural rows carry
`student_ref = HMAC-SHA256(pepper, user_id)` only; the pepper lives in `.env`, is never
committed, and is never stored in the Lab database. No model needs a student's name to analyse
a question.

**The local production snapshot** MUST live on FileVault-encrypted storage, outside the
repository, outside any cloud-synced folder, in global gitignore, never copied to another
machine or VPS, and always stamped with `snapshot_taken_at`. Note the measured exposure:
`personal_access_tokens` (~24,408) and `social_providers` (~17,369) may contain **live
credentials**, which makes disk encryption urgent rather than procedural.

**Lab-owned public surfaces collect no PII by design**: no login, no email, no phone, no name —
only a random first-party session id, and nothing is collected that does not feed a declared
metric.

**Retrieved document text is data, never instructions.** PDF and OCR content is passed in a
delimited data field; system prompts MUST state that instructions inside retrieved content are
ignored; generator output is schema-validated and never executed.

**Question provenance is tracked, not assumed.** Every question carries `source_origin`
(`authored` / `book_derived` / `unknown` / `suspected_official`), defaulting to `unknown`.
Generation is grounded in licensed course material; the model is never asked to reproduce real
exam items.

**Human review decisions are the most valuable data in the system.** Nightly `pg_dump` to
encrypted local storage, a weekly off-machine copy, and at least one verified restore drill
before any group review session begins.

**Any conflicting duplicate or negatively-discriminating item is a live student-facing error**
and enters a high-priority trainer queue immediately, with the count of affected students — it
does not wait for the rest of a review batch.

## Development Workflow & Quality Gates

- **Spec-driven flow.** Work proceeds `/speckit-constitution` → `/speckit-specify` →
  `/speckit-clarify` → `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`, one feature
  branch per project increment. Specs cite plan sections (Principle II).
- **Constitution Check is a gate**, evaluated before Phase 0 research and re-evaluated after
  Phase 1 design. A violation is either removed or justified in the Complexity Tracking table
  with the simpler alternative and why it was rejected.
- **Definition of Done** for any increment: acceptance criteria checked, unit tests for
  deterministic logic passing, `lab:health` green including the two inverted checks, guardrail
  tests passing, evals re-run if any model/prompt/normalization/embedding/chunking changed,
  and documentation plus ADRs updated in the same change.
- **Reporting is literal.** If a check was skipped, say so. If a metric is below its gate, say
  so and stop. Claiming completion without a passing verification run is a violation of this
  constitution, not a style issue.
- **Measure before building.** Any project that depends on bank statistics MUST run the §6
  profiling pack first and update the affected plan sections with real numbers.
- **Human reviewers are scheduled, not summoned.** Review budgets are agreed before a project
  that consumes them begins; trainers are reserved for domain judgement (answer correctness,
  question quality, taxonomy) and moderators for repetitive operational review.

## Governance

**Supremacy.** This constitution supersedes ad-hoc practice, personal preference, and habit.
Where it conflicts with a plan document, the conflict MUST be resolved explicitly — either the
plan is amended or an ADR records the exception — never resolved silently in code.

**Amendment procedure.**

1. Propose the change in writing with the principle affected and the concrete rule text.
2. State the impact on existing plans, projects in flight, and templates.
3. Update `.specify/memory/constitution.md` and propagate to
   `.specify/templates/plan-template.md`, `spec-template.md`, `tasks-template.md`, and any
   runtime guidance file in the same commit.
4. Record the Sync Impact Report at the top of this file.

**Versioning policy** (semantic):

- **MAJOR** — a principle is removed or redefined in a backward-incompatible way, or a
  governance rule changes such that already-approved work would now be non-compliant.
- **MINOR** — a new principle or section is added, or existing guidance is materially expanded.
- **PATCH** — clarification, wording, typo, or non-semantic refinement.

**Compliance review.** Every plan, task list, and code review MUST verify compliance. Reviewers
check specifically: plan traceability (II), project scope boundaries (III), deterministic
computation and structured output (IV), the required tests and nothing beyond them (V), Arabic
RTL plus `n`-with-every-number plus AI-labelled output (VI), memory and LLM-call budgets (VII),
and the data-protection constraints, which are non-waivable.

**Runtime guidance.** `CLAUDE.md` and `AGENTS.md` point contributors and agents to the current
plan; they carry pointers, never a second copy of these rules. Complexity MUST be justified
against the principle it strains — the default answer is the simpler option.

**Version**: 1.0.0 | **Ratified**: 2026-08-19 | **Last Amended**: 2026-08-19
