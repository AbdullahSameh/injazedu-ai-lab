# InjazEdu AI Lab — Lean Planning & Implementation Review

Before starting P1, review the project's planning and governance documents and simplify them according to the decisions below.

## Context

* P0 — `docs/plans/project/0/p0-ai-lab-foundation.md` — is already implemented and completed.
* P1 — `docs/plans/project/1/p1-production-profiling-and-question-mirror.md` — is next.
* I use the following Spec Kit workflow only:

```text
/speckit.specify
/speckit.plan
/speckit.tasks
/speckit.implement
```

* I am a solo developer using Claude Code and Codex.
* The priority is working software, architecture, business/domain logic, maintainable code, and practical AI Lab progress — not process-heavy governance.

---

# Operator Decisions — Treat These as Final

## 1. Production database copy

I already have a local copy of the InjazEdu Production database.

Its data is current up to approximately:

```text
2026-08-07
```

This copy will be used for the **entire local AI Lab development program**.

Do **not** require or propose:

* taking another Production snapshot,
* refreshing this copy before P1/P2/etc.,
* scheduled snapshot refreshes,
* Production backup procedures,
* gates that block development because the copy is old.

The age of the data may be recorded as context where relevant, but it must not block development.

I am free to inspect, query, transform, or modify this local copy as needed for building and experimenting with the AI Lab.

Production itself remains out of scope for writes unless a future project explicitly changes that decision.

---

# Engineering Priorities

Prioritize:

1. Correct business/domain logic.
2. Clean architecture and clear responsibility boundaries.
3. Maintainable, readable code.
4. Appropriate design patterns where they provide real value.
5. Reliable data processing.
6. AI/LLM, embeddings, RAG, PDF, analytics, and assessment functionality.
7. Useful developer tooling.
8. Tests for important behavior and risky logic.

Avoid spending significant implementation time on process artifacts that do not materially improve the software.

---

# Lean Documentation Policy

From now on, documentation should follow:

```text
Code first.
Tests for important behavior.
Documentation only when it has continuing practical value.
```

Do not create documentation merely because a process normally expects it.

Avoid adding new:

* reports,
* runbooks,
* ADRs,
* governance documents,
* acceptance gates,
* handover documents,
* checklists,
* duplicated planning documents,

unless there is a clear practical reason.

Prefer updating an existing document over creating another one.

## Runbooks

Create or keep a runbook only when it describes a **real manual operational process that I am likely to perform again**.

Examples that may justify a runbook:

* starting/recovering the local stack if non-obvious,
* running a complicated data import,
* future Production deployment or recovery.

Things such as implementation decisions, memory observations, normal development commands, phase summaries, or explanations of code should normally live in:

* the code,
* tests,
* configuration,
* README,
* or the relevant project/spec plan.

They should not get separate runbooks.

## ADRs

ADRs are exceptional.

Create or retain an ADR only for a decision that is:

```text
architectural
+ long-lived
+ important
+ expensive to reverse
```

Ordinary implementation decisions belong in the project plan, code, or configuration.

## Gates

Keep only gates that protect a real engineering property or prevent an expensive/dangerous failure.

Examples:

* preventing unintended Production writes,
* preventing PII from entering inappropriate Lab storage,
* ETL correctness,
* idempotency where re-running an operation is expected,
* model/evaluation quality thresholds when unreliable AI output would affect users.

Remove or downgrade procedural gates such as:

* mandatory report writing,
* documentation-review gates,
* handover gates,
* memory-number gates,
* snapshot-age gates,
* gates whose only purpose is satisfying another document.

---

# Testing Policy

Automated tests should focus on important behavior.

Prioritize tests for:

* domain/business logic,
* transformations and derivations,
* data integrity,
* security boundaries,
* important failure cases,
* ETL behavior such as idempotency/resume when required,
* AI structured-output contracts where appropriate.

Do not attempt exhaustive test coverage of trivial framework behavior, simple getters, basic UI wiring, or functionality already strongly guaranteed by the framework.

Testing should reduce meaningful risk, not maximize test count.

---

# Required Review

Review the following before implementing P1:

## 1. Constitution

Review and simplify the current constitution.

Update it to reflect the lean approach outlined above.

Keep only durable engineering principles.

Remove or simplify rules that create governance/process overhead without protecting important architecture, correctness, security, or data integrity.

---

## 2. Core plans

Review:

```text
docs/plans/core/injazedu_ai_assessment_engagement_lab_full_plan.md

docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md
```

The **final v2 plan is the governing plan**.

The older v1 plan is historical/background context and must not override v2.

Do not rewrite the entire core plan unnecessarily.

Make only changes needed to remove contradictions with the new operator decisions, especially:

* repeated snapshot refresh requirements,
* backup requirements,
* unnecessary documentation requirements,
* excessive governance gates,
* excessive ADR/runbook expectations.

Preserve the actual:

* project goals,
* architecture,
* dependencies,
* AI strategy,
* data strategy,
* evaluation strategy,
* important security boundaries.

---

## 3. P1 plan

Review:

```text
docs/plans/project/1/p1-production-profiling-and-question-mirror.md
```

Update it for the lean implementation model.

Preserve useful technical requirements from the existing plan, but remove unnecessary ceremony.

In particular, reconsider requirements whose primary output is another document rather than working functionality.

---

# File Preservation Rule — Mandatory

Do NOT delete any file under:

```text
docs/plans/
docs/schema/
docs/prompts/
```

Files in these directories may be updated, simplified, marked historical/superseded, or reorganized only if safe, but **must not be removed**.

This is a hard constraint.

---

# Expected Result

After the review, the repository should have:

```text
Lean constitution
        ↓
Final core plan (v2)
        ↓
P1 project plan
```

The planning system should support implementation rather than become a project in its own right.

When deciding whether to add a task, gate, test, ADR, report, or runbook, use this question:

> Does this materially improve the correctness, safety, maintainability, architecture, or practical operation of the software?

If the answer is no, omit it.

---

# How to Perform This Review

1. Inspect the existing constitution and the three planning documents before editing.
2. Identify contradictions with the operator decisions above.
3. Simplify rather than rewrite where possible.
4. Preserve valuable engineering requirements.
5. Remove process-only overhead.
6. Update P1 so one Spec covers the complete project.
7. Do not start implementing P1 during this review unless explicitly instructed afterward.
8. At completion, give me a concise summary of:

   * what you changed,
   * what gates/runbooks/ADRs were removed or retained and why,
   * any important engineering requirement you deliberately kept,
   * any unresolved decision that genuinely affects implementation.


Also, is there any history related to `005-profiling-and-mirror-schema` in any of (.specify, .claude, .agents, .opencode, AGENTS.md, CLAUDE.md) clean it.