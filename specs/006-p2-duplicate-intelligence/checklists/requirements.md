# Specification Quality Checklist: P2 — Arabic Normalization & Duplicate Intelligence

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-08-27
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs) — **deliberate deviation, see Notes**
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders — **deviation: written for the operator, see Notes**
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details) — **same deviation**
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification — **same deviation**

## Notes

**The three technology-agnosticism items are deliberately deviated from, and the deviation is the
project's own rule.** Named technologies in this spec — the hash function, the vector and trigram
extensions, the 768-dimension embedding contract, the JSON-Schema-constrained verdict, the exact-scan
default — are **binding constraints handed down** by `.specify/memory/constitution.md` (III, IV, VII)
and by §12–§13 and §17 of the program plan, not choices this feature makes. Restating them
abstractly ("a content-addressed fingerprint", "a similarity index") would hide the fact that they
are fixed and that changing one is a stop-and-ask. The predecessor spec
(`005-p1-profiling-and-question-mirror`) sets the same precedent, and the constitution's
"Artefacts earn their place" clause governs.

Where the spec *does* keep implementation out is the level that matters: it names no class, no file
path, no migration, no method signature. Those live in `plan.md`.

**Audience.** This is a single-developer, local-only program. The stakeholder and the implementer are
the same person, and the spec is written for that reader — plus the trainer and moderator whose time
the human gates commit.

**Clarification session, 2026-08-27 — five questions asked, five answered.** Recorded in the spec's
`## Clarifications` section and integrated as FR-116 to FR-138, SC-026 to SC-029, four acceptance
scenarios and two edge cases:

1. **Cluster shape** — transitive closure within each source layer, one cluster per question per
   layer, oversized components flagged rather than merged. Closed a real hole: pairwise clusters
   would have double-counted `affected_student_count`.
2. **Verdict-call failure** — bounded retries across runs, then a terminal state. Closed a second
   hole: a permanently null verdict was indistinguishable from "not yet judged", so the pair would
   have been re-dispatched forever, breaking idempotency and re-spending the rationed budget.
3. **Review action → cluster status** — the five actions map to status and relation type per FR-128;
   a decision on an `urgent_review` cluster sets `resolved`; the model's verdict stays untouched.
4. **Two relation vocabularies** — confirmed as deliberate and promoted from assumption to
   requirement (FR-132 to FR-134).
5. **The daily review cap** — a soft cap on the ongoing queues with the calibration set exempt. This
   closed a **constitutional compliance gap**: principle VI requires a daily cap and the spec had
   only the ordering half of that rule.

**Operator decisions, 2026-08-28 — three taken, one constitutional conflict surfaced rather than
absorbed.** Recorded as a second session in the spec's `## Clarifications` and integrated as FR-139 to
FR-154, SC-031 to SC-034, six amended FRs, four amended SCs, seven acceptance scenarios and three
assumptions. Evidence is notes.md N10:

1. **Normalization** — the strict path is unchanged and `ة → ه` stays out of every hash; a named
   **recall-only** fold tolerates the typo and can never produce an `exact_duplicate`. Option labels
   are stripped in **both scripts** (3,604 Latin-labelled options against 1,200 Arabic; 9.9% of the
   bank has no Arabic character). Case folding, absent from FR-011 while N1's embedding budget assumed
   it, is now explicit — a genuine requirement/measurement disagreement, caught before implementation.
2. **Progressive calibration** — waves of 100 to the same 400 ceiling, with a 95% Wilson interval as
   the stopping rule. This **raises** the gate rather than relaxing it: FR-060 passed on a point
   estimate, FR-144 passes only when the interval clears. Humans remain the sole ground truth; the AI
   pre-label is stored separately **and** hidden until the human commits, because separate storage
   protects the data while blind-first protects the labeller.
3. **The conflict backlog blocks nothing** — human gate F was a **procedural gate** by the
   constitution's own definition and is reclassified to configuration. The backlog ships tiered and
   standing at `daily_review_cap = 10`; the top 100 of 928 groups carry 50.4% of measured exposure.

**A constitutional conflict was escalated, not smoothed.** Constitution IV forbade `ة → ه` without
qualification, and decision 1 required it. Rather than reinterpret a binding invariant, the conflict
was put to the operator, who approved narrowing IV to the property it protects — the strict layers and
every hash, cluster key and identity decision — while admitting a named recall-only form.
**Constitution is now v2.5.0.** It is the only rule in the program this feature changed.

**Judgement calls still standing as assumptions** (both low-impact, neither touches the schema):

- **What populates `purpose = 'uncertain_review'`** — verdicts flagged `review_required`, which is
  exactly the population §8 item E budgets. The plan defines the mode but not its source.
- **What `lab:dedup` with no `--step` does** — run the unconditional steps in dependency order and
  stop with a clear message at the first step whose human input is missing, rather than calibrating
  against absent labels.

**Two numeric inconsistencies in the source plan** are recorded in Assumptions rather than silently
smoothed: its duplicate-group tallies (4,689 vs 4,602 + 136) and its conflicting-group counts
(~1,227 raw vs 1,125 no-image) do not reconcile. Both are treated as planning figures; the derive and
cluster steps record the exact measured values.
